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
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

class MicrosoftGraphTransport extends AbstractTransport
{
    protected const MAX_INLINE_ATTACHMENT_SIZE = 3.5 * 1024 * 1024;
    protected const LARGE_ATTACHMENT_CHUNK_SIZE = 1024 * 1024;

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

        [$attachments, $largeAttachments, $html] = $this->prepareAttachments($email, $html);

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
        ];

        $from = $envelope->getSender()->getAddress();

        if (filled($headers = $this->getInternetMessageHeaders($email))) {
            $payload['message']['internetMessageHeaders'] = $headers;
        }

        $skipLargeAttachments = config('mail.mailers.microsoft-graph.skip_large_attachments', false);
        if ($skipLargeAttachments || count($largeAttachments) === 0) {
            $this->microsoftGraphApiService->sendMail($from, $payload);

            return;
        }

        $graphMessageResponse = $this->microsoftGraphApiService->saveMessage($from, $payload['message']);
        $graphMessage = $graphMessageResponse->json();
        $graphMessageId = $graphMessage['id'];

        $this->prepareLargeAttachments($largeAttachments, $from, $graphMessageId);

        $this->microsoftGraphApiService->sendMessage($from, $graphMessageId);
    }

    /**
     * @return array<int, array<int<0, max>, array<string, bool|string|null>>|string|null, DataPart[]>
     */
    protected function prepareAttachments(Email $email, ?string $html): array
    {
        $attachments = [];
        $largeAttachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $content = $attachment->getBody();
            $contentSize = strlen($content);

            if ($contentSize > self::MAX_INLINE_ATTACHMENT_SIZE) {
                $largeAttachments[] = $attachment;
                continue;
            }

            $headers = $attachment->getPreparedHeaders();
            $fileName = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $fileName,
                'contentType' => $attachment->getMediaType(),
                'contentBytes' => base64_encode($content),
                'contentId' => $fileName,
                'isInline' => $headers->getHeaderBody('Content-Disposition') === 'inline',
            ];
        }

        return [$attachments, $largeAttachments, $html];
    }

    /**
     * @param Collection<array-key, Address> $recipients
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
            ->filter(fn (Address $address) => !in_array($address, array_merge($email->getCc(), $email->getBcc()), true));
    }

    /**
     * Transforms given Symfony Headers
     * to Microsoft Graph internet message headers
     * see https://learn.microsoft.com/en-us/graph/api/resources/internetmessageheader?view=graph-rest-1.0
     */
    protected function getInternetMessageHeaders(Email $email): ?array
    {
        return collect($email->getHeaders()->all())
            ->filter(fn (HeaderInterface $header) => str_starts_with($header->getName(), 'X-'))
            ->map(fn (HeaderInterface $header) => ['name' => $header->getName(), 'value' => $header->getBodyAsString()])
            ->values()
            ->all() ?: null;
    }

    /**
     * @param DataPart[] $largeAttachments
     * @param string $from
     * @param string $graphMessageId
     * @return void
     */
    private function prepareLargeAttachments(array $largeAttachments, string $from, string $graphMessageId): void
    {
        foreach ($largeAttachments as $attachment) {
            $content = $attachment->getBody();
            $contentSize = strlen($content);

            $uploadSessionResponse = $this->microsoftGraphApiService->createUploadSession($from, $graphMessageId, [
                'AttachmentItem' => [
                    'attachmentType' => 'file',
                    'name' => $attachment->getFilename(),
                    'size' => $contentSize,
                ]
            ]);
            $uploadSession = $uploadSessionResponse->json();
            $uploadUrl = $uploadSession['uploadUrl'];

            $this->uploadLargeAttachment($uploadUrl, $content, $contentSize);
        }
    }

    private function uploadLargeAttachment(string $uploadUrl, string $content, int $contentSize): void
    {
        $totalChunks = ceil($contentSize / self::LARGE_ATTACHMENT_CHUNK_SIZE);
        $start = 0;

        for ($chunkIndex = 0; $chunkIndex < $totalChunks; $chunkIndex++) {
            $end = min($start + self::LARGE_ATTACHMENT_CHUNK_SIZE - 1, $contentSize - 1);
            $chunk = substr($content, $start, self::LARGE_ATTACHMENT_CHUNK_SIZE);

            $this->microsoftGraphApiService->uploadChunk($uploadUrl, $chunk, $start, $end, $contentSize);

            $start = $end + 1;
        }
    }
}
