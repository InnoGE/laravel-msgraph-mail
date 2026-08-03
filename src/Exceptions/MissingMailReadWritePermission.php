<?php

namespace InnoGE\LaravelMsGraphMail\Exceptions;

use RuntimeException;

class MissingMailReadWritePermission extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Sending this message requires the Mail.ReadWrite application permission: '
            .'its attachments exceed the Microsoft Graph sendMail size limit, so the message '
            .'must be created as a draft and the attachments uploaded in chunks. Grant the '
            .'Mail.ReadWrite application permission (plus admin consent) to the Azure app '
            .'registration, or reduce the attachment size.'
        );
    }
}
