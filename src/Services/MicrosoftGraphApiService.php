<?php

namespace InnoGE\LaravelMsGraphMail\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InnoGE\LaravelMsGraphMail\Exceptions\InvalidResponse;

class MicrosoftGraphApiService
{
    public function __construct(
        protected readonly string $tenantId,
        protected readonly string $clientId,
        protected readonly string $clientSecret,
        protected readonly int $accessTokenTtl
    ) {
    }

    /**
     * @throws RequestException
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
        return Cache::remember('microsoft-graph-api-access-token', $this->accessTokenTtl, function (): string {
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
            if (! is_string($accessToken)) {
                $notString = var_export($accessToken, true);
                throw new InvalidResponse("Expected response to contain key access_token of type string, got: {$notString}.");
            }

            return $accessToken;
        });
    }
}
