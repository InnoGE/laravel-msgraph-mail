<?php

namespace VictoRD11\LaravelMsGraphMail\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use VictoRD11\LaravelMsGraphMail\Contracts\TokenProviderInterface;
use VictoRD11\LaravelMsGraphMail\Exceptions\InvalidResponse;

class ClientCredentialsTokenProvider implements TokenProviderInterface
{
    public function __construct(
        protected readonly string $tenantId,
        protected readonly string $clientId,
        protected readonly string $clientSecret,
        protected readonly int $accessTokenTtl
    ) {}

    public function getAccessToken(): string
    {
        return Cache::remember('microsoft-graph-api-client-credentials-access-token', $this->accessTokenTtl, function (): string {
            $response = Http::asForm()
                ->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                ]);

            $response->throw();

            $accessToken = $response->json('access_token');
            throw_unless(is_string($accessToken), new InvalidResponse('Expected response to contain key access_token of type string, got: '.var_export($accessToken, true).'.'));

            return $accessToken;
        });
    }
}
