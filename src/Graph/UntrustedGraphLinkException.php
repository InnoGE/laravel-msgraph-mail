<?php

namespace InnoGE\LaravelMsGraphMail\Graph;

use RuntimeException;

final class UntrustedGraphLinkException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Refusing to send the Microsoft Graph token to a link outside the Microsoft Graph API.');
    }
}
