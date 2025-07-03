<?php

namespace VictoRD11\LaravelMsGraphMail\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use VictoRD11\LaravelMsGraphMail\Contracts\TokenProviderInterface;

class MicrosoftGraphApiService
{
    public function __construct(
        protected readonly TokenProviderInterface $tokenProvider
    ) {}

    public function sendMail(string $from, array $payload): Response
    {
        return $this->getBaseRequest()
            ->post("/users/{$from}/sendMail", $payload)
            ->throw();
    }

    protected function getBaseRequest(): PendingRequest
    {
        return Http::withToken($this->tokenProvider->getAccessToken())
            ->baseUrl('https://graph.microsoft.com/v1.0');
    }
}
