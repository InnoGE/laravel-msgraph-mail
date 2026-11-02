<?php

namespace InnoGE\LaravelMsGraphMail\Graph;

final readonly class GraphCredentials
{
    public function __construct(
        public string $tenantId,
        public string $clientId,
        public ClientAuthentication $authentication,
    ) {
        if ($tenantId === '' || $clientId === '') {
            throw new InvalidCredentialsException('The tenant id and client id must not be empty.');
        }
    }
}
