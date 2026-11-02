<p align="center">
    <img src="docs/logo.png" alt="Laravel MsGraph Mail" width="900">
</p>

# Laravel Microsoft Graph Mail

[![Latest Version on Packagist](https://img.shields.io/packagist/v/innoge/laravel-msgraph-mail.svg?style=flat-square)](https://packagist.org/packages/innoge/laravel-msgraph-mail)
[![Laravel Version](https://img.shields.io/badge/laravel-12.x%7C13.x-orange)](https://packagist.org/packages/innoge/laravel-msgraph-mail)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/innoge/laravel-msgraph-mail/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/innoge/laravel-msgraph-mail/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/innoge/laravel-msgraph-mail.svg?style=flat-square)](https://packagist.org/packages/innoge/laravel-msgraph-mail)

- [Introduction](#introduction)
- [Installation](#installation)
    - [Registering the Entra App](#registering-the-entra-app)
    - [Configuring the Mailer](#configuring-the-mailer)
    - [Certificate Authentication](#certificate-authentication)
- [Sending Mail](#sending-mail)
    - [Saving Mail to Sent Items](#saving-mail-to-sent-items)
    - [Large Attachments](#large-attachments)
- [Using the Graph Client](#using-the-graph-client)
- [AI Agent Support](#ai-agent-support)
- [Upgrading](#upgrading)

<a name="introduction"></a>
## Introduction

Microsoft 365 is retiring basic authentication for SMTP, and sending through a shared Exchange mailbox with a password
was never a great idea anyway. Laravel Microsoft Graph Mail gives Laravel a `microsoft-graph` mail transport that sends
your mail through the Microsoft Graph API instead, authenticated as an Entra app with a client secret or a certificate.

Your mailables, notifications and `Mail::to(...)` calls stay exactly as they are. You only switch the mailer:

```ini
MAIL_MAILER=microsoft-graph
```

Large attachments are sent through Microsoft Graph upload sessions automatically, so a 20 MB PDF works just like a
two-line password reset.

<a name="installation"></a>
## Installation

The package requires PHP 8.3+ and Laravel 12 or 13. Install it with Composer:

```shell
composer require innoge/laravel-msgraph-mail
```

<a name="registering-the-entra-app"></a>
### Registering the Entra App

The transport authenticates as a Microsoft Entra app with an application permission:

1. In the [Entra admin center](https://entra.microsoft.com), go to **App registrations** and create a new
   registration. Note the **Application (client) ID** and the **Directory (tenant) ID**.
2. Under **Certificates & secrets**, create a client secret, or upload a certificate if you'd rather not rotate
   secrets (see [Certificate Authentication](#certificate-authentication)).
3. Under **API permissions**, add the Microsoft Graph **application** permission `Mail.Send` and grant admin consent.
   If you send [large attachments](#large-attachments), add `Mail.ReadWrite` as well.
4. Limit the app to the mailboxes it may send from. An application permission covers every mailbox in your tenant by
   default, so restrict it with [RBAC for Applications](https://learn.microsoft.com/exchange/permissions-exo/application-rbac)
   or an Application Access Policy.

Prefer a walkthrough with screenshots? There's a [blog post](https://geisi.dev/blog/getting-rid-of-deprecated-microsoft-office-365-smtp-mail-sending)
covering every step.

<a name="configuring-the-mailer"></a>
### Configuring the Mailer

Add a `microsoft-graph` mailer to the `mailers` array of your `config/mail.php` configuration file:

```php
'microsoft-graph' => [

    /*
     * The transport registered by this package. Do not change it.
     */
    'transport' => 'microsoft-graph',

    /*
     * The Directory (tenant) ID and the Application (client) ID of your Entra app registration.
     */
    'tenant_id' => env('MICROSOFT_GRAPH_TENANT_ID'),
    'client_id' => env('MICROSOFT_GRAPH_CLIENT_ID'),

    /*
     * A client secret of the app registration. Leave it out when you authenticate with a certificate
     * instead, which takes precedence once it is filled.
     */
    'client_secret' => env('MICROSOFT_GRAPH_CLIENT_SECRET'),

    /*
     * Optional. The sender of every mail sent through this mailer. Omit the whole key to use the global
     * mail.from configuration. The address must be the primary SMTP address of a real mailbox in your tenant.
     */
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS'),
        'name' => env('MAIL_FROM_NAME'),
    ],

    /*
     * Optional. Whether sent mail is kept in the sender's "Sent Items" folder. Defaults to false;
     * single mailables may override it.
     */
    'save_to_sent_items' => env('MAIL_SAVE_TO_SENT_ITEMS', false),
],
```

Then add your credentials to the `.env` file and switch the default mailer:

```ini
MAIL_MAILER=microsoft-graph

MICROSOFT_GRAPH_TENANT_ID=
MICROSOFT_GRAPH_CLIENT_ID=
MICROSOFT_GRAPH_CLIENT_SECRET=
```

<a name="certificate-authentication"></a>
### Certificate Authentication

Client secrets expire, and an expired secret means mail silently stops going out. Certificates are the more robust
choice. Upload the certificate to your app registration under **Certificates & secrets**, then add a
`client_certificate` entry to the mailer:

```php
'microsoft-graph' => [
    'transport' => 'microsoft-graph',
    'tenant_id' => env('MICROSOFT_GRAPH_TENANT_ID'),
    'client_id' => env('MICROSOFT_GRAPH_CLIENT_ID'),

    /*
     * The certificate uploaded to the app registration and its private key, each as PEM content or a
     * local file path. The passphrase is only needed for an encrypted private key.
     */
    'client_certificate' => [
        'certificate' => env('MICROSOFT_GRAPH_CERTIFICATE'),
        'private_key' => env('MICROSOFT_GRAPH_PRIVATE_KEY'),
        'passphrase' => env('MICROSOFT_GRAPH_KEY_PASSPHRASE'),
    ],
],
```

Certificate authentication requires the `openssl` PHP extension. The certificate and key are read when the mailer is
created, so a broken key fails right away with an `InvalidCredentialsException` rather than on the first send. File
paths must point to local files, and no exception message ever contains your key.

<a name="sending-mail"></a>
## Sending Mail

There's nothing new to learn. Once the mailer is configured, send mail the way you always do:

```php
Mail::to($user)->send(new OrderShipped($order));
```

If `microsoft-graph` isn't your default mailer, pick it explicitly:

```php
Mail::mailer('microsoft-graph')->to($user)->send(new OrderShipped($order));
```

<a name="saving-mail-to-sent-items"></a>
### Saving Mail to Sent Items

By default, sent mail isn't kept in the sender's "Sent Items" folder. You may change the default with the
`save_to_sent_items` option, or decide per mailable through a Symfony metadata header:

```php
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Email;

$mailable->withSymfonyMessage(function (Email $message) {
    $message->getHeaders()->add(new MetadataHeader('save-to-sent-items', 'true'));
});
```

<a name="large-attachments"></a>
### Large Attachments

Microsoft Graph rejects `sendMail` requests larger than about 4 MB. When your mail is bigger, the package creates a
draft, uploads the attachments in chunks through [upload sessions](https://learn.microsoft.com/graph/outlook-large-attachments)
and then sends the draft. You don't have to do anything, but this path needs the `Mail.ReadWrite` application
permission. Without it, the package throws a `MissingMailReadWritePermissionException` that tells you exactly that.

Microsoft always keeps a sent draft in "Sent Items". When `save_to_sent_items` is disabled, the package removes it
again afterwards, and a failed send cleans up its draft.

<a name="using-the-graph-client"></a>
## Using the Graph Client

The authenticated client behind the transport is available for your own Microsoft Graph calls. It caches its access
token, URL-encodes path segments, and only ever sends the token to the Microsoft Graph API:

```php
use InnoGE\LaravelMsGraphMail\Graph\ClientSecret;
use InnoGE\LaravelMsGraphMail\Graph\GraphClient;
use InnoGE\LaravelMsGraphMail\Graph\GraphCredentials;

$graph = new GraphClient(new GraphCredentials(
    tenantId: config('services.graph.tenant_id'),
    clientId: config('services.graph.client_id'),
    authentication: new ClientSecret(config('services.graph.client_secret')),
));

$messages = $graph->request()
    ->get($graph->path('users', 'support@example.com', 'messages'))
    ->throw()
    ->json('value');
```

[innoge/laravel-msgraph-mailbox](https://github.com/innoge/laravel-msgraph-mailbox) is built on this client. If you'd
like to receive mail as well, it turns Microsoft 365 mailboxes into Laravel events.

<a name="ai-agent-support"></a>
## AI Agent Support

The package ships a [Laravel Boost](https://laravel.com/docs/boost) skill covering configuration, troubleshooting and
the Entra app registration. Boost installs it through `php artisan boost:install` or `php artisan boost:update --discover`.

<a name="upgrading"></a>
## Upgrading

Upgrading from 1.x or 2.x? The [upgrade guide](UPGRADE.md) walks you through every change.

## Testing

```shell
composer test
```

## Changelog

Please see the [CHANGELOG](CHANGELOG.md) for what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Tim Geisendoerfer](https://github.com/InnoGE)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see the [License File](LICENSE.md) for more information.
