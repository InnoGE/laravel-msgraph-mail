<?php

namespace InnoGE\LaravelMsGraphMail;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use InnoGE\LaravelMsGraphMail\Exceptions\InvalidConfigurationException;
use InnoGE\LaravelMsGraphMail\Exceptions\MissingConfigurationException;
use InnoGE\LaravelMsGraphMail\Graph\ClientAuthentication;
use InnoGE\LaravelMsGraphMail\Graph\ClientCertificate;
use InnoGE\LaravelMsGraphMail\Graph\ClientSecret;
use InnoGE\LaravelMsGraphMail\Graph\GraphClient;
use InnoGE\LaravelMsGraphMail\Graph\GraphCredentials;
use SensitiveParameter;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MsGraphMailServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-msgraph-mail');
    }

    public function boot(): void
    {
        Mail::extend('microsoft-graph', function (array $config): MicrosoftGraphTransport {
            /** @var array<string, mixed> $config */
            $from = array_key_exists('from', $config) ? $config['from'] : config('mail.from');
            throw_if(blank(data_get($from, 'address')), new MissingConfigurationException('from.address'));

            return new MicrosoftGraphTransport(
                new MailApi(new GraphClient($this->credentials($config))),
                saveToSentItems: filter_var($config['save_to_sent_items'] ?? false, FILTER_VALIDATE_BOOLEAN),
            );
        });
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function credentials(#[SensitiveParameter] array $config): GraphCredentials
    {
        return new GraphCredentials(
            tenantId: $this->requireString($config, 'tenant_id'),
            clientId: $this->requireString($config, 'client_id'),
            authentication: $this->authentication($config),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function authentication(#[SensitiveParameter] array $config): ClientAuthentication
    {
        if (blank(data_get($config, 'client_certificate.certificate'))) {
            return new ClientSecret($this->requireString($config, 'client_secret'));
        }

        $passphrase = data_get($config, 'client_certificate.passphrase');
        if ($passphrase !== null && ! is_string($passphrase)) {
            throw new InvalidConfigurationException('client_certificate.passphrase', 'expected a string, got '.get_debug_type($passphrase));
        }

        return new ClientCertificate(
            certificate: $this->requireString($config, 'client_certificate.certificate'),
            privateKey: $this->requireString($config, 'client_certificate.private_key'),
            passphrase: $passphrase,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return non-empty-string
     */
    private function requireString(#[SensitiveParameter] array $config, string $key): string
    {
        if (! Arr::has($config, $key)) {
            throw new MissingConfigurationException($key);
        }

        $value = data_get($config, $key);
        if (! is_string($value) || $value === '') {
            throw new InvalidConfigurationException($key, 'expected a non-empty string, got '.($value === '' ? 'an empty string' : get_debug_type($value)));
        }

        return $value;
    }
}
