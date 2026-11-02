<?php

namespace InnoGE\LaravelMsGraphMail\Exceptions;

use Exception;

class InvalidConfigurationException extends Exception
{
    public function __construct(string $key, string $reason)
    {
        parent::__construct("Configuration key {$key} for microsoft-graph mailer is invalid: {$reason}.");
    }
}
