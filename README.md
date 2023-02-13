# Laravel Microsoft Graph Mail Driver Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/innoge/laravel-msgraph-mail.svg?style=flat-square)](https://packagist.org/packages/innoge/laravel-msgraph-mail)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/innoge/laravel-msgraph-mail/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/innoge/laravel-msgraph-mail/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/innoge/laravel-msgraph-mail/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/innoge/laravel-msgraph-mail/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/innoge/laravel-msgraph-mail.svg?style=flat-square)](https://packagist.org/packages/innoge/laravel-msgraph-mail)

This package provides a Microsoft Graph mail driver for Laravel. It is an alternative when you don't want to use the
deprecated and unsecure Basic Auth SMTP driver with Microsoft Office 365.

## Installation

You can install the package via composer:

```bash
composer require innoge/laravel-msgraph-mail
```
### Compatibility
Laravel 10.x and 9.x are supported.

## Configuration

### Register the Azure App

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
],
```

For the `client_id`, `client_secret` and `tenant_id` you need to use the values from the Azure App you created in the
previous step.

Now you can switch your default mail driver to the new `microsoft-graph` driver by setting the env variable:

```dotenv
MAIL_MAILER=microsoft-graph
```

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

- [Tim Geisendoerfer](https://github.com/InnoGE)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
