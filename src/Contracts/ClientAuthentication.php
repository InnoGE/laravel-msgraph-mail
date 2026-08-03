<?php

namespace InnoGE\LaravelMsGraphMail\Contracts;

interface ClientAuthentication
{
    /**
     * @return array<string, string>
     */
    public function tokenRequestParameters(string $clientId, string $tokenEndpoint): array;
}
