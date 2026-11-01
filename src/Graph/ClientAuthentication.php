<?php

namespace InnoGE\LaravelMsGraphMail\Graph;

interface ClientAuthentication
{
    /**
     * @return array<string, string>
     */
    public function tokenRequestParameters(string $clientId, string $tokenEndpoint): array;
}
