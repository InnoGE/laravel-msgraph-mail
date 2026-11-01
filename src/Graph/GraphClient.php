<?php

namespace InnoGE\LaravelMsGraphMail\Graph;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;

final class GraphClient
{
    private const string BASE_URL = 'https://graph.microsoft.com/v1.0';

    private const int TOKEN_EXPIRATION_BUFFER = 60;

    /**
     * @param  list<string>  $preferences
     */
    public function __construct(
        private readonly GraphCredentials $credentials,
        private readonly array $preferences = [],
    ) {}

    /**
     * Create an authenticated request against the Microsoft Graph API.
     */
    public function request(string ...$preferences): PendingRequest
    {
        $request = Http::withToken($this->accessToken())
            ->baseUrl(self::BASE_URL)
            ->withRequestMiddleware(fn (RequestInterface $request) => Str::startsWith((string) $request->getUri(), self::BASE_URL.'/')
                ? $request
                : throw new UntrustedGraphLinkException);

        $preferences = [...$this->preferences, ...$preferences];

        return $preferences === [] ? $request : $request->withHeaders(['Prefer' => implode(', ', $preferences)]);
    }

    /**
     * Build a Microsoft Graph path from the given segments, URL-encoding each of them.
     */
    public function path(string ...$segments): string
    {
        foreach ($segments as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                throw new InvalidArgumentException('Microsoft Graph path segments must not be empty, "." or "..".');
            }
        }

        return '/'.implode('/', array_map(fn (string $segment) => Str::replace('%40', '@', rawurlencode($segment)), $segments));
    }

    private function accessToken(): string
    {
        $cacheKey = "microsoft-graph-api-access-token-{$this->credentials->tenantId}-{$this->credentials->clientId}";

        $accessToken = Cache::get($cacheKey);
        if (is_string($accessToken)) {
            return $accessToken;
        }

        $tokenEndpoint = "https://login.microsoftonline.com/{$this->credentials->tenantId}/oauth2/v2.0/token";

        $response = Http::asForm()
            ->post($tokenEndpoint, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->credentials->clientId,
                'scope' => 'https://graph.microsoft.com/.default',
                ...$this->credentials->authentication->tokenRequestParameters($this->credentials->clientId, $tokenEndpoint),
            ])
            ->throw();

        $accessToken = $response->json('access_token');
        throw_unless(is_string($accessToken), new InvalidResponseException('Expected the token response to contain key access_token of type string, got: '.get_debug_type($accessToken).'.'));

        Cache::put($cacheKey, $accessToken, $this->tokenCacheTtl($response->json('expires_in')));

        return $accessToken;
    }

    private function tokenCacheTtl(mixed $expiresIn): int
    {
        $expiresIn = is_numeric($expiresIn) ? (int) $expiresIn : 3600;

        return max($expiresIn - self::TOKEN_EXPIRATION_BUFFER, self::TOKEN_EXPIRATION_BUFFER);
    }
}
