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
            throw_if(blank($config['from']['address'] ?? []), new ConfigurationMissing('from.address'));

            $accessTokenTtl = $config['access_token_ttl'] ?? 3000;
            if (! is_int($accessTokenTtl)) {
                throw new ConfigurationInvalid('access_token_ttl', $accessTokenTtl);
            }

            return new MicrosoftGraphTransport(
                new MicrosoftGraphApiService(
                    tenantId: $this->requireConfigString($config, 'tenant_id'),
                    clientId: $this->requireConfigString($config, 'client_id'),
                    clientSecret: $this->requireConfigString($config, 'client_secret'),
                    accessTokenTtl: $accessTokenTtl,
                ),
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
