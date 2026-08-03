<?php

namespace InnoGE\LaravelMsGraphMail\Contracts;

interface ClientAuthentication
{
    /**
     * The client-authentication specific form parameters merged into the
     * OAuth2 client-credentials token request.
     *
     * @return array<string, string>
     */
    public function tokenRequestParameters(string $clientId, string $tokenEndpoint): array;
}
