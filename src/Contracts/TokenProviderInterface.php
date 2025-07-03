<?php

namespace VictoRD11\LaravelMsGraphMail\Contracts;

interface TokenProviderInterface
{
    public function getAccessToken(): string;
}
