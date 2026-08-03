<?php

namespace InnoGE\LaravelMsGraphMail;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use InnoGE\LaravelMsGraphMail\Exceptions\MissingMailReadWritePermission;
use InnoGE\LaravelMsGraphMail\Services\MicrosoftGraphApiService;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

class MicrosoftGraphTransport extends AbstractTransport
{
    public const SAVE_TO_SENT_ITEMS_METADATA = 'save-to-sent-items';

    /**
     * Estimated request bytes above which sendMail would exceed Graph's ~4 MB
     * request cap and the message is sent via a draft + upload sessions instead.
     */
    protected const SIMPLE_SEND_LIMIT = 3_000_000;

    /**
     * Raw attachment bytes above which an attachment must be uploaded through
     * an upload session instead of a direct attachments POST.
     */
    protected const LARGE_ATTACHMENT_SIZE = 3_000_000;

    public function __construct(
        protected MicrosoftGraphApiService $microsoftGraphApiService,
        protected bool $saveToSentItems = false,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($dispatcher, $logger);
    }

    public function __toString(): string
    {
        return 'microsoft+graph+api://';
    }

    /**
     * @throws RequestException
     */
    protected function doSend(SentMessage $message): void
    {
        $originalMessage = $message->getOriginalMessage();
        if (! $originalMessage instanceof Message) {
            throw new LogicException(sprintf('Expected the original message to be an instance of %s, got %s.', Message::class, get_debug_type($originalMessage)));
        }

        $email = MessageConverter::toEmail($originalMessage);
        $envelope = $message->getEnvelope();

        $html = $this->bodyToString($email->getHtmlBody());

        $attachments = $this->prepareAttachments($email);

        $payload = [
            'message' => [
                'subject' => $email->getSubject(),
                'body' => [
                    'contentType' => $html === null ? 'Text' : 'HTML',
                    'content' => $html ?: $this->bodyToString($email->getTextBody()),
                ],
                'toRecipients' => $this->transformEmailAddresses($this->getRecipients($email, $envelope)),
                'ccRecipients' => $this->transformEmailAddresses(collect($email->getCc())),
                'bccRecipients' => $this->transformEmailAddresses(collect($email->getBcc())),
                'replyTo' => $this->transformEmailAddresses(collect($email->getReplyTo())),
                'sender' => $this->transformEmailAddress($envelope->getSender()),
                'attachments' => $attachments,
            ],
            'saveToSentItems' => $this->shouldSaveToSentItems($email),
        ];

        if (filled($headers = $this->getInternetMessageHeaders($email))) {
            $payload['message']['internetMessageHeaders'] = $headers;
        }

        $from = $envelope->getSender()->getAddress();

        if (strlen((string) json_encode($payload)) > self::SIMPLE_SEND_LIMIT) {
            $this->sendViaDraft($from, $payload['message'], $attachments);

            return;
        }

        $this->microsoftGraphApiService->sendMail($from, $payload);
    }

    /**
     * Requires the Mail.ReadWrite application permission. A sent draft is
     * always stored in Sent Items — Graph has no saveToSentItems control here.
     *
     * @param  array<string, mixed>  $message
     * @param  list<array{'@odata.type': string, name: string|null, contentType: string, contentBytes: string, contentId: string|null, isInline: bool}>  $attachments
     */
    protected function sendViaDraft(string $from, array $message, array $attachments): void
    {
        unset($message['attachments']);

        try {
            $messageId = $this->microsoftGraphApiService->createDraftMessage($from, $message);
        } catch (RequestException $exception) {
            throw $exception->response->status() === 403 ? new MissingMailReadWritePermission : $exception;
        }

        foreach ($attachments as $attachment) {
            $contents = (string) base64_decode($attachment['contentBytes'], true);

            if (strlen($contents) > self::LARGE_ATTACHMENT_SIZE) {
                $this->microsoftGraphApiService->uploadAttachment($from, $messageId, array_filter([
                    'attachmentType' => 'file',
                    'name' => $attachment['name'],
                    'size' => strlen($contents),
                    'contentType' => $attachment['contentType'],
                    'isInline' => $attachment['isInline'],
                    'contentId' => $attachment['contentId'],
                ], fn (mixed $value): bool => $value !== null), $contents);
            } else {
                $this->microsoftGraphApiService->addAttachment($from, $messageId, $attachment);
            }
        }

        $this->microsoftGraphApiService->sendDraftMessage($from, $messageId);
    }

    /**
     * @return list<array{'@odata.type': string, name: string|null, contentType: string, contentBytes: string, contentId: string|null, isInline: bool}>
     */
    protected function prepareAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $fileName = $headers->getHeaderParameter('Content-Disposition', 'filename');
            $contentIdHeaderBody = $headers->has('Content-ID') ? $headers->get('Content-ID')?->getBody() : null;
            $contentId = is_array($contentIdHeaderBody) ? ($contentIdHeaderBody[0] ?? null) : null;
            $contentId = is_string($contentId) && filled($contentId) ? $contentId : null;

            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                // Some clients (e.g. Thunderbird) only render inline images whose name has an extension.
                'name' => $fileName ?? $contentId,
                'contentType' => implode('/', [$attachment->getMediaType(), $attachment->getMediaSubtype()]),
                'contentBytes' => base64_encode($attachment->getBody()),
                'contentId' => $contentId ?? $fileName,
                'isInline' => $headers->getHeaderBody('Content-Disposition') === 'inline',
            ];
        }

        return $attachments;
    }

    /**
     * Per-message override via MetadataHeader('save-to-sent-items', …).
     */
    protected function shouldSaveToSentItems(Email $email): bool
    {
        $header = $email->getHeaders()->get('X-Metadata-'.self::SAVE_TO_SENT_ITEMS_METADATA);

        if ($header instanceof MetadataHeader) {
            return filter_var($header->getValue(), FILTER_VALIDATE_BOOLEAN);
        }

        return $this->saveToSentItems;
    }

    /**
     * @param  resource|string|null  $body
     */
    protected function bodyToString(mixed $body): ?string
    {
        if (is_string($body) || $body === null) {
            return $body;
        }

        return stream_get_contents($body) ?: null;
    }

    /**
     * @param  Collection<array-key, Address>  $recipients
     * @return array<array-key, array{emailAddress: array{address: string}}>
     */
    protected function transformEmailAddresses(Collection $recipients): array
    {
        return $recipients
            ->map(fn (Address $recipient) => $this->transformEmailAddress($recipient))
            ->all();
    }

    /**
     * @return array{emailAddress: array{address: string}}
     */
    protected function transformEmailAddress(Address $address): array
    {
        return [
            'emailAddress' => [
                'address' => $address->getAddress(),
            ],
        ];
    }

    /**
     * @return Collection<array-key, Address>
     */
    protected function getRecipients(Email $email, Envelope $envelope): Collection
    {
        return collect($envelope->getRecipients())
            ->filter(fn (Address $address) => ! in_array($address, array_merge($email->getCc(), $email->getBcc()), true));
    }

    /**
     * @see https://learn.microsoft.com/en-us/graph/api/resources/internetmessageheader?view=graph-rest-1.0
     *
     * @return list<array{name: string, value: string}>|null
     */
    protected function getInternetMessageHeaders(Email $email): ?array
    {
        $headers = [];
        foreach ($email->getHeaders()->all() as $header) {
            // Metadata and tag headers carry transport instructions and are not part of the message.
            if ($header instanceof MetadataHeader || $header instanceof TagHeader) {
                continue;
            }

            if ($header instanceof HeaderInterface && str_starts_with($header->getName(), 'X-')) {
                $headers[] = ['name' => $header->getName(), 'value' => $header->getBodyAsString()];
            }
        }

        return $headers ?: null;
    }
}
