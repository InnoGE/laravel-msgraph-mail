# Laravel Microsoft Graph Mail Driver Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/innoge/laravel-msgraph-mail.svg?style=flat-square)](https://packagist.org/packages/innoge/laravel-msgraph-mail)
[![Laravel Version](https://img.shields.io/badge/laravel-11.x%7C12.x%7C13.x-orange)](https://packagist.org/packages/innoge/laravel-msgraph-mail)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/innoge/laravel-msgraph-mail/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/innoge/laravel-msgraph-mail/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/innoge/laravel-msgraph-mail/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/innoge/laravel-msgraph-mail/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/innoge/laravel-msgraph-mail.svg?style=flat-square)](https://packagist.org/packages/innoge/laravel-msgraph-mail)

This package provides a Microsoft Graph mail driver for Laravel. It is an alternative when you don't want to use the
deprecated and unsecure Basic Auth SMTP driver with Microsoft Office 365.

Version 2.x supports PHP 8.2+ and Laravel 11–13, authenticates with a client secret or a client certificate, and
automatically sends large attachments through Microsoft Graph upload sessions. Upgrading from 1.x? See
[UPGRADE.md](UPGRADE.md).

## Installation

You can install the package via composer:

```bash
composer require innoge/laravel-msgraph-mail
```

## Configuration

### Register the Azure App

### Microsoft Azure AD Configuration

I have written a detailed Blog Post how you can configure your Microsoft Azure AD Tenant. [Sending Mails with Laravel and Microsoft Office 365 the secure way](https://geisi.dev/blog/getting-rid-of-deprecated-microsoft-office-365-smtp-mail-sending)

### I want to figure it out on my own

You need to register an Azure App in your Azure AD tenant. You can do this by following the steps in
the [Microsoft Graph documentation](https://docs.microsoft.com/en-us/graph/auth-register-app-v2).

After creating the App you have to add the following permissions to the App:
Mail.Send (Application permission) you will find it under the "Microsoft Graph" section.

Now you have to Grant Admin Consent for the App. You can do this by following the steps in
the [Microsoft Graph documentation](https://docs.microsoft.com/en-us/graph/auth-v2-service#3-get-administrator-consent).

### Configuring your Laravel app

First you need to add a new entry to the mail drivers array in your `config/mail.php` configuration file:

```php
'microsoft-graph' => [
    'transport' => 'microsoft-graph',
    'client_id' => env('MICROSOFT_GRAPH_CLIENT_ID'),
    'client_secret' => env('MICROSOFT_GRAPH_CLIENT_SECRET'),
    'tenant_id' => env('MICROSOFT_GRAPH_TENANT_ID'),
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS'),
        'name' => env('MAIL_FROM_NAME'),
    ],
    'save_to_sent_items' =>  env('MAIL_SAVE_TO_SENT_ITEMS', false),
],
```

For the `client_id`, `client_secret` and `tenant_id` you need to use the values from the Azure App you created in the
previous step.

The `from` block is optional: when it is omitted entirely, the global `mail.from` configuration is used. The from
address must be the primary SMTP address of a real mailbox in your tenant.

Now you can switch your default mail driver to the new `microsoft-graph` driver by setting the env variable:

```dotenv
MAIL_MAILER=microsoft-graph
```

### Certificate authentication

Instead of a client secret you can authenticate with a client certificate (no more expiring secrets). Upload the
certificate to the Azure app registration (Certificates & secrets → Certificates), then configure:

```php
'microsoft-graph' => [
    'transport' => 'microsoft-graph',
    'client_id' => env('MICROSOFT_GRAPH_CLIENT_ID'),
    'tenant_id' => env('MICROSOFT_GRAPH_TENANT_ID'),
    'client_certificate' => [
        'certificate' => env('MICROSOFT_GRAPH_CERTIFICATE'),      // PEM content or file path
        'private_key' => env('MICROSOFT_GRAPH_PRIVATE_KEY'),      // PEM content or file path
        'passphrase' => env('MICROSOFT_GRAPH_KEY_PASSPHRASE'),    // optional
    ],
    // ...
],
```

Both values accept either raw PEM content (starting with `-----BEGIN`) or a path to a PEM file. When
`client_certificate` is configured it takes precedence over `client_secret`. Certificate authentication requires the
`openssl` PHP extension.

### Saving mails to Sent Items

The `save_to_sent_items` option controls whether sent mail is stored in the sender mailbox's "Sent Items" folder
(default: `false`). Individual mailables can override the configured default through a Symfony metadata header:

```php
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Email;

$mailable->withSymfonyMessage(function (Email $message) {
    $message->getHeaders()->add(new MetadataHeader('save-to-sent-items', 'true'));
});
```

### Large attachments

Messages whose total size exceeds Microsoft Graph's ~4 MB `sendMail` request limit are sent automatically through a
draft message with chunked [attachment upload sessions](https://learn.microsoft.com/en-us/graph/outlook-large-attachments).
This path additionally requires the **`Mail.ReadWrite`** application permission (plus admin consent). Two things to
know:

- Without `Mail.ReadWrite`, large sends throw a `MissingMailReadWritePermission` exception explaining the fix.
- Draft-based sends are always stored in Sent Items — Graph offers no `saveToSentItems` control on this path.

## AI Agent Support

This package ships with a [Laravel Boost](https://laravel.com/docs/boost) skill (`msgraph-mail`) covering configuration, troubleshooting, and a step-by-step Azure app registration guide. If your project uses Boost, the skill is installed automatically via `php artisan boost:install` or `php artisan boost:update --discover`.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Tim Geisendoerfer](https://github.com/InnoGE)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
