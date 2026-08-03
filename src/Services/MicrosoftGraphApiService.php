<?php

namespace InnoGE\LaravelMsGraphMail\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InnoGE\LaravelMsGraphMail\Contracts\ClientAuthentication;
use InnoGE\LaravelMsGraphMail\Exceptions\InvalidResponse;

class MicrosoftGraphApiService
{
    /**
     * Seconds subtracted from the token lifetime so a token is refreshed
     * before it can expire mid-request.
     */
    protected const TOKEN_EXPIRATION_BUFFER = 60;

    /**
     * Maximum bytes uploaded per chunk in an upload session (multiple of 320 KiB).
     */
    protected const UPLOAD_CHUNK_SIZE = 3_276_800;

    public function __construct(
        protected readonly string $tenantId,
        protected readonly string $clientId,
        protected readonly ClientAuthentication $authentication,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendMail(string $from, array $payload): Response
    {
        return $this->getBaseRequest()
            ->post("/users/{$from}/sendMail", $payload)
            ->throw();
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{id: string, internetMessageId: string}
     */
    public function createDraftMessage(string $from, array $message): array
    {
        $response = $this->getBaseRequest()
            ->post("/users/{$from}/messages", $message)
            ->throw();

        $id = $response->json('id');
        $internetMessageId = $response->json('internetMessageId');

        throw_unless(is_string($id), new InvalidResponse('Expected draft message response to contain key id of type string, got: '.var_export($id, true).'.'));
        throw_unless(is_string($internetMessageId), new InvalidResponse('Expected draft message response to contain key internetMessageId of type string, got: '.var_export($internetMessageId, true).'.'));

        return ['id' => $id, 'internetMessageId' => $internetMessageId];
    }

    /**
     * Find the id of a sent (non-draft) message by its stable internet message
     * id. Message ids change when a sent draft moves to Sent Items, so this is
     * the only reliable way to locate the sent copy.
     */
    public function findSentMessageId(string $from, string $internetMessageId): ?string
    {
        $messages = $this->getBaseRequest()
            ->get("/users/{$from}/messages", [
                '$filter' => "internetMessageId eq '".str_replace("'", "''", $internetMessageId)."'",
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
        $uploadUrl = $this->getBaseRequest()
            ->post("/users/{$from}/messages/{$messageId}/attachments/createUploadSession", [
                'AttachmentItem' => $attachmentItem,
            ])
            ->throw()
            ->json('uploadUrl');

        throw_unless(is_string($uploadUrl), new InvalidResponse('Expected upload session response to contain key uploadUrl of type string, got: '.var_export($uploadUrl, true).'.'));

        $totalSize = strlen($contents);

        foreach (str_split($contents, self::UPLOAD_CHUNK_SIZE) as $index => $chunk) {
            $rangeStart = $index * self::UPLOAD_CHUNK_SIZE;
            $rangeEnd = $rangeStart + strlen($chunk) - 1;

            // The upload URL is pre-authenticated; Graph rejects requests
            // carrying an Authorization header here.
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
        $this->getBaseRequest()
            ->post("/users/{$from}/messages/{$messageId}/attachments", $attachment)
            ->throw();
    }

    public function sendDraftMessage(string $from, string $messageId): void
    {
        $this->getBaseRequest()
            ->post("/users/{$from}/messages/{$messageId}/send")
            ->throw();
    }

    /**
     * A regular DELETE only moves the message to Deleted Items; permanentDelete
     * leaves no copy behind.
     */
    public function permanentlyDeleteMessage(string $from, string $messageId): void
    {
        $this->getBaseRequest()
            ->post("/users/{$from}/messages/{$messageId}/permanentDelete")
            ->throw();
    }

    protected function getBaseRequest(): PendingRequest
    {
        return Http::withToken($this->getAccessToken())
            ->baseUrl('https://graph.microsoft.com/v1.0');
    }

    protected function getAccessToken(): string
    {
        $cacheKey = "microsoft-graph-api-access-token-{$this->tenantId}-{$this->clientId}";

        $accessToken = Cache::get($cacheKey);
        if (is_string($accessToken)) {
            return $accessToken;
        }

        $tokenEndpoint = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";

        $response = Http::asForm()
            ->post($tokenEndpoint, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'scope' => 'https://graph.microsoft.com/.default',
                ...$this->authentication->tokenRequestParameters($this->clientId, $tokenEndpoint),
            ]);

        $response->throw();

        $accessToken = $response->json('access_token');
        throw_unless(is_string($accessToken), new InvalidResponse('Expected response to contain key access_token of type string, got: '.var_export($accessToken, true).'.'));

        Cache::put($cacheKey, $accessToken, $this->tokenCacheTtl($response->json('expires_in')));

        return $accessToken;
    }

    protected function tokenCacheTtl(mixed $expiresIn): int
    {
        $expiresIn = is_numeric($expiresIn) ? (int) $expiresIn : 3600;

        return max($expiresIn - self::TOKEN_EXPIRATION_BUFFER, self::TOKEN_EXPIRATION_BUFFER);
    }
}
