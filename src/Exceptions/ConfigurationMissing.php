<?php

namespace InnoGE\LaravelMsGraphMail\Exceptions;

use Exception;

class ConfigurationMissing extends Exception
{
    public function __construct(string $key)
    {
        parent::__construct("Configuration key {$key} for microsoft-graph mailer is missing.");
    }
}
