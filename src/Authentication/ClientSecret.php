<?php

namespace InnoGE\LaravelMsGraphMail\Authentication;

use InnoGE\LaravelMsGraphMail\Contracts\ClientAuthentication;

class ClientSecret implements ClientAuthentication
{
    public function __construct(
        protected readonly string $clientSecret,
    ) {}

    public function tokenRequestParameters(string $clientId, string $tokenEndpoint): array
    {
        return [
            'client_secret' => $this->clientSecret,
        ];
    }
}
