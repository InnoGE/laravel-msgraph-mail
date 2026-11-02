<?php

namespace InnoGE\LaravelMsGraphMail;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InnoGE\LaravelMsGraphMail\Graph\GraphClient;
use InnoGE\LaravelMsGraphMail\Graph\InvalidResponseException;

class MailApi
{
    protected const UPLOAD_CHUNK_SIZE = 3_276_800;

    public function __construct(
        protected readonly GraphClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendMail(string $from, array $payload): Response
    {
        return $this->client->request()
            ->post($this->client->path('users', $from, 'sendMail'), $payload)
            ->throw();
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{id: string, internetMessageId: string}
     */
    public function createDraftMessage(string $from, array $message): array
    {
        $response = $this->client->request()
            ->post($this->client->path('users', $from, 'messages'), $message)
            ->throw();

        $id = $response->json('id');
        $internetMessageId = $response->json('internetMessageId');

        throw_unless(is_string($id), new InvalidResponseException('Expected draft message response to contain key id of type string, got: '.get_debug_type($id).'.'));
        throw_unless(is_string($internetMessageId), new InvalidResponseException('Expected draft message response to contain key internetMessageId of type string, got: '.get_debug_type($internetMessageId).'.'));

        return ['id' => $id, 'internetMessageId' => $internetMessageId];
    }

    public function findSentMessageId(string $from, string $internetMessageId): ?string
    {
        $messages = $this->client->request()
            ->get($this->client->path('users', $from, 'messages'), [
                '$filter' => "internetMessageId eq '".Str::replace("'", "''", $internetMessageId)."'",
                '$select' => 'id,isDraft',
                '$top' => '5',
            ])
            ->throw()
            ->json('value');

        foreach (is_array($messages) ? $messages : [] as $message) {
            if (is_array($message) && ($message['isDraft'] ?? null) === false && is_string($message['id'] ?? null)) {
                return $message['id'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attachmentItem
     */
    public function uploadAttachment(string $from, string $messageId, array $attachmentItem, string $contents): void
    {
        $uploadUrl = $this->client->request()
            ->post($this->client->path('users', $from, 'messages', $messageId, 'attachments', 'createUploadSession'), [
                'AttachmentItem' => $attachmentItem,
            ])
            ->throw()
            ->json('uploadUrl');

        throw_unless(is_string($uploadUrl), new InvalidResponseException('Expected upload session response to contain key uploadUrl of type string, got: '.get_debug_type($uploadUrl).'.'));

        $totalSize = strlen($contents);

        foreach (str_split($contents, self::UPLOAD_CHUNK_SIZE) as $index => $chunk) {
            $rangeStart = $index * self::UPLOAD_CHUNK_SIZE;
            $rangeEnd = $rangeStart + strlen($chunk) - 1;

            Http::withHeaders([
                'Content-Range' => "bytes {$rangeStart}-{$rangeEnd}/{$totalSize}",
            ])
                ->withBody($chunk, 'application/octet-stream')
                ->put($uploadUrl)
                ->throw();
        }
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    public function addAttachment(string $from, string $messageId, array $attachment): void
    {
        $this->client->request()
            ->post($this->client->path('users', $from, 'messages', $messageId, 'attachments'), $attachment)
            ->throw();
    }

    public function sendDraftMessage(string $from, string $messageId): void
    {
        $this->client->request()
            ->post($this->client->path('users', $from, 'messages', $messageId, 'send'))
            ->throw();
    }

    public function permanentlyDeleteMessage(string $from, string $messageId): void
    {
        $this->client->request()
            ->post($this->client->path('users', $from, 'messages', $messageId, 'permanentDelete'))
            ->throw();
    }
}
