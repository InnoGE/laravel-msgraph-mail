<?php

namespace InnoGE\LaravelMsGraphMail\Graph;

use SensitiveParameter;

final readonly class ClientSecret implements ClientAuthentication
{
    public function __construct(#[SensitiveParameter] private string $clientSecret)
    {
        if ($clientSecret === '') {
            throw new InvalidCredentialsException('The client secret must not be empty.');
        }
    }

    public function tokenRequestParameters(string $clientId, string $tokenEndpoint): array
    {
        return ['client_secret' => $this->clientSecret];
    }
}
