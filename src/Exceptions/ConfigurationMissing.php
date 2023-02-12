<?php

namespace InnoGE\LaravelMsGraphMail\Exceptions;

use Exception;

class ConfigurationMissing extends Exception
{
    public static function tenantId(): self
    {
        return new static('The tenant id is missing from the configuration file.');
    }

    public static function clientId(): self
    {
        return new static('The client id is missing from the configuration file.');
    }

    public static function clientSecret(): self
    {
        return new static('The client secret is missing from the configuration file.');
    }
}
