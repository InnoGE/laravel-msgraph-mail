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
    public function __construct(protected MicrosoftGraphApiService $microsoftGraphApiService, protected string $from, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
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

        $payload = [
            'message' => [
                'subject' => $email->getSubject(),
                'body' => [
                    'contentType' => $email->getHtmlBody() === null ? 'Text' : 'HTML',
                    'content' => $email->getHtmlBody() ?: $email->getTextBody(),
                ],
                'toRecipients' => $this->transformEmailAddresses($this->getRecipients($email, $envelope)),
                'ccRecipients' => $this->transformEmailAddresses(collect($email->getCc())),
                'bccRecipients' => $this->transformEmailAddresses(collect($email->getBcc())),
                'replyTo' => $this->transformEmailAddresses(collect($email->getReplyTo())),
                'sender' => $this->transformEmailAddress($envelope->getSender()),
                'attachments' => $this->getAttachments($email),
            ],
            'saveToSentItems' => false,
        ];

        $this->microsoftGraphApiService->sendMail($this->from, $payload);
    }

    /**
     * @param  Collection<Address>  $recipients
     * @return array
     */
    protected function transformEmailAddresses(Collection $recipients): array
    {
        return $recipients
            ->map(fn (Address $recipient) => $this->transformEmailAddress($recipient))
            ->toArray();
    }

    /**
     * @param  Address  $address
     * @return array
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
     * @param  Email  $email
     * @param  Envelope  $envelope
     * @return Collection<Address>
     */
    protected function getRecipients(Email $email, Envelope $envelope): Collection
    {
        return collect($envelope->getRecipients())
            ->filter(fn (Address $address) => ! in_array($address, array_merge($email->getCc(), $email->getBcc()), true));
    }

    protected function getAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $fileName = $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename');
            $attachments[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $fileName,
                'contentType' => $attachment->getMediaType(),
                'contentBytes' => base64_encode($attachment->getBody()),
            ];
        }

        return $attachments;
    }
}
