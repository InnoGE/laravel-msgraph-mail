<?php

namespace InnoGE\LaravelMsGraphMail;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use InnoGE\LaravelMsGraphMail\Authentication\ClientCertificate;
use InnoGE\LaravelMsGraphMail\Authentication\ClientSecret;
use InnoGE\LaravelMsGraphMail\Contracts\ClientAuthentication;
use InnoGE\LaravelMsGraphMail\Exceptions\ConfigurationInvalid;
use InnoGE\LaravelMsGraphMail\Exceptions\ConfigurationMissing;
use InnoGE\LaravelMsGraphMail\Services\MicrosoftGraphApiService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMsGraphMailServiceProvider extends PackageServiceProvider
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
            // Mirror MailManager::setGlobalAddress(): the global `mail.from` is only
            // applied when the mailer config omits the `from` key entirely — a present
            // but empty `from` would not fall back and the send would fail later.
            $from = array_key_exists('from', $config) ? $config['from'] : config('mail.from');
            throw_if(blank(data_get($from, 'address')), new ConfigurationMissing('from.address'));

            return new MicrosoftGraphTransport(
                new MicrosoftGraphApiService(
                    tenantId: $this->requireConfigString($config, 'tenant_id'),
                    clientId: $this->requireConfigString($config, 'client_id'),
                    authentication: $this->clientAuthentication($config),
                ),
                saveToSentItems: filter_var($config['save_to_sent_items'] ?? false, FILTER_VALIDATE_BOOLEAN),
            );
        });
    }

    /**
     * A configured client_certificate takes precedence over client_secret.
     *
     * @param  array<string, mixed>  $config
     */
    protected function clientAuthentication(array $config): ClientAuthentication
    {
        if (filled($config['client_certificate'] ?? null)) {
            $passphrase = data_get($config, 'client_certificate.passphrase');
            if ($passphrase !== null && ! is_string($passphrase)) {
                throw new ConfigurationInvalid('client_certificate.passphrase', $passphrase);
            }

            return new ClientCertificate(
                certificate: $this->requireConfigString($config, 'client_certificate.certificate'),
                privateKey: $this->requireConfigString($config, 'client_certificate.private_key'),
                passphrase: $passphrase,
            );
        }

        return new ClientSecret($this->requireConfigString($config, 'client_secret'));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return non-empty-string
     */
    protected function requireConfigString(array $config, string $key): string
    {
        if (! Arr::has($config, $key)) {
            throw new ConfigurationMissing($key);
        }

        $value = data_get($config, $key);
        if (! is_string($value) || $value === '') {
            throw new ConfigurationInvalid($key, $value);
        }

        return $value;
    }
}
