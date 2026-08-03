<?php

namespace InnoGE\LaravelMsGraphMail;

use Illuminate\Support\Facades\Mail;
use InnoGE\LaravelMsGraphMail\Exceptions\ConfigurationInvalid;
use InnoGE\LaravelMsGraphMail\Exceptions\ConfigurationMissing;
use InnoGE\LaravelMsGraphMail\Services\MicrosoftGraphApiService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelMsGraphMailServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
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
                    clientSecret: $this->requireConfigString($config, 'client_secret'),
                ),
                saveToSentItems: filter_var($config['save_to_sent_items'] ?? false, FILTER_VALIDATE_BOOLEAN),
            );
        });
    }

    /**
     * @param  array<string, mixed>  $config
     * @return non-empty-string
     */
    protected function requireConfigString(array $config, string $key): string
    {
        if (! array_key_exists($key, $config)) {
            throw new ConfigurationMissing($key);
        }

        $value = $config[$key];
        if (! is_string($value) || $value === '') {
            throw new ConfigurationInvalid($key, $value);
        }

        return $value;
    }
}
