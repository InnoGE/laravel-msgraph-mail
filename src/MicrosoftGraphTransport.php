<?php

namespace InnoGE\LaravelMsGraphMail;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use InnoGE\LaravelMsGraphMail\Services\MicrosoftGraphApiService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
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
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $html = $email->getHtmlBody();

        [$attachments, $html] = $this->prepareAttachments($email, $html);

        $payload = [
            'message' => [
                'subject' => $email->getSubject(),
                'body' => [
                    'contentType' => $html === null ? 'Text' : 'HTML',
                    'content' => $html ?: $email->getTextBody(),
                ],
                'toRecipients' => $this->transformEmailAddresses($this->getRecipients($email, $envelope)),
                'ccRecipients' => $this->transformEmailAddresses(collect($email->getCc())),
                'bccRecipients' => $this->transformEmailAddresses(collect($email->getBcc())),
                'replyTo' => $this->transformEmailAddresses(collect($email->getReplyTo())),
                'sender' => $this->transformEmailAddress($envelope->getSender()),
                'attachments' => $attachments,
            ],
            'saveToSentItems' => config('mail.mailers.microsoft-graph.save_to_sent_items', false) ?? false,
            'internetMessageHeaders' => $this->toInternetMessageHeaders($message->getHeaders()),

        ];

        $this->microsoftGraphApiService->sendMail($envelope->getSender()->getAddress(), $payload);
    }

    /**
     * @return array<int, array<int<0, max>, array<string, bool|string|null>>|string|null>
     */
    protected function prepareAttachments(Email $email, ?string $html): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $fileName = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $fileName,
                'contentType' => $attachment->getMediaType(),
                'contentBytes' => base64_encode($attachment->getBody()),
                'contentId' => $fileName,
                'isInline' => $headers->getHeaderBody('Content-Disposition') === 'inline',
            ];
        }

        return [$attachments, $html];
    }

    /**
     * @param  Collection<array-key, Address>  $recipients
     * @return array<array-key, array<string, array<string, string>>>
     */
    protected function transformEmailAddresses(Collection $recipients): array
    {
        return $recipients
            ->map(fn (Address $recipient) => $this->transformEmailAddress($recipient))
            ->toArray();
    }

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
     * Transforms given Swift Mime SimpleHeaderSet into
     * Microsoft Graph internet message headers
     * @param \Swift_Mime_SimpleHeaderSet $headers
     * @return array|null
     */
    protected function toInternetMessageHeaders(\Swift_Mime_SimpleHeaderSet $headers): ?array {
        $customHeaders = [];

        foreach ($headers->getAll() as $header) {
            $name = $header->getFieldName();
            $body = $header->getFieldBody();

            if (isset($name, $body) && str_starts_with($name, 'X-')) {
                $customHeaders[] = [
                    'name' => $name,
                    'value' => $body,
                ];
            }
        }

        return count($customHeaders) > 0
            ? $customHeaders
            : null;
    }
}
