# This is my package laravel-msgraph-mail

[![Latest Version on Packagist](https://img.shields.io/packagist/v/innoge/laravel-msgraph-mail.svg?style=flat-square)](https://packagist.org/packages/innoge/laravel-msgraph-mail)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/innoge/laravel-msgraph-mail/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/innoge/laravel-msgraph-mail/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/innoge/laravel-msgraph-mail/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/innoge/laravel-msgraph-mail/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/innoge/laravel-msgraph-mail.svg?style=flat-square)](https://packagist.org/packages/innoge/laravel-msgraph-mail)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-msgraph-mail.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-msgraph-mail)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require innoge/laravel-msgraph-mail
```

First you need to add a new entry to the mail drivers array in your config/mail.php configuration file:

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
