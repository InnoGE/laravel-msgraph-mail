<?php

namespace InnoGE\LaravelMsGraphMail\Exceptions;

use Exception;

class ConfigurationMissing extends Exception
{
    public static function tenantId(): self
    {
        return new self('The tenant id is missing from the configuration file.');
    }

    public static function clientId(): self
    {
        return new self('The client id is missing from the configuration file.');
    }

    public static function clientSecret(): self
    {
        return new self('The client secret is missing from the configuration file.');
    }

    public static function fromAddress(): self
    {
        return new self('The mail from address is missing from the configuration file.');
    }
}
