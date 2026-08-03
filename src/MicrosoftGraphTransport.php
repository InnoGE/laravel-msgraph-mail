<?php

namespace InnoGE\LaravelMsGraphMail;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use InnoGE\LaravelMsGraphMail\Services\MicrosoftGraphApiService;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(
        protected MicrosoftGraphApiService $microsoftGraphApiService,
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
            'saveToSentItems' => config('mail.mailers.microsoft-graph.save_to_sent_items', false) ?? false,
        ];

        if (filled($headers = $this->getInternetMessageHeaders($email))) {
            $payload['message']['internetMessageHeaders'] = $headers;
        }

        $this->microsoftGraphApiService->sendMail($envelope->getSender()->getAddress(), $payload);
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
            // Laravel 12.44.0 bug: Contains a new Content-ID Header with the CID for inline attachments fallback to regular logic using filename for unaffected versions
            $contentIdHeaderBody = $headers->has('Content-ID') ? $headers->get('Content-ID')?->getBody() : null;
            $contentId = is_array($contentIdHeaderBody) ? ($contentIdHeaderBody[0] ?? null) : null;
            $contentId = is_string($contentId) && filled($contentId) ? $contentId : $fileName;

            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $contentId,
                'contentType' => implode('/', [$attachment->getMediaType(), $attachment->getMediaSubtype()]),
                'contentBytes' => base64_encode($attachment->getBody()),
                'contentId' => $contentId,
                'isInline' => $headers->getHeaderBody('Content-Disposition') === 'inline',
            ];
        }

        return $attachments;
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
     * Transforms given Symfony Headers
     * to Microsoft Graph internet message headers
     * see https://learn.microsoft.com/en-us/graph/api/resources/internetmessageheader?view=graph-rest-1.0
     *
     * @return list<array{name: string, value: string}>|null
     */
    protected function getInternetMessageHeaders(Email $email): ?array
    {
        $headers = [];
        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof HeaderInterface && str_starts_with($header->getName(), 'X-')) {
                $headers[] = ['name' => $header->getName(), 'value' => $header->getBodyAsString()];
            }
        }

        return $headers ?: null;
    }
}
