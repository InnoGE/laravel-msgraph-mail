<?php

namespace InnoGE\LaravelMsGraphMail\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InnoGE\LaravelMsGraphMail\Exceptions\InvalidResponse;

class MicrosoftGraphApiService
{
    /**
     * Seconds subtracted from the token lifetime so a token is refreshed
     * before it can expire mid-request.
     */
    protected const TOKEN_EXPIRATION_BUFFER = 60;

    public function __construct(
        protected readonly string $tenantId,
        protected readonly string $clientId,
        protected readonly string $clientSecret,
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

        $response = Http::asForm()
            ->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                ]);

        $response->throw();

        $accessToken = $response->json('access_token');
        throw_unless(is_string($accessToken), new InvalidResponse('Expected response to contain key access_token of type string, got: '.var_export($accessToken, true).'.'));

        Cache::put($cacheKey, $accessToken, $this->tokenCacheTtl($response->json('expires_in')));

        return $accessToken;
    }

    /**
     * Cache the token for its actual lifetime minus a safety buffer.
     */
    protected function tokenCacheTtl(mixed $expiresIn): int
    {
        $expiresIn = is_numeric($expiresIn) ? (int) $expiresIn : 3600;

        return max($expiresIn - self::TOKEN_EXPIRATION_BUFFER, self::TOKEN_EXPIRATION_BUFFER);
    }
}
