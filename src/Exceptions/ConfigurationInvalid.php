<?php

namespace InnoGE\LaravelMsGraphMail\Exceptions;

use Exception;

class ConfigurationInvalid extends Exception
{
    public function __construct(string $key, mixed $value)
    {
        $invalidValue = var_export($value, true);
        parent::__construct("Configuration key {$key} for microsoft-graph mailer has invalid value: {$invalidValue}.");
    }
}
