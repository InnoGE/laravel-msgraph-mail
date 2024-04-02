<?php

namespace InnoGE\LaravelMsGraphMail;

use Illuminate\Support\Facades\Mail;
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
        $this->app->bind(MicrosoftGraphApiService::class, function () {
            //throw exceptions when config is missing
            throw_if(blank(config('mail.mailers.microsoft-graph.tenant_id')), ConfigurationMissing::tenantId());
            throw_if(blank(config('mail.mailers.microsoft-graph.client_id')), ConfigurationMissing::clientId());
            throw_if(blank(config('mail.mailers.microsoft-graph.client_secret')), ConfigurationMissing::clientSecret());

            return new MicrosoftGraphApiService(
                tenantId: config('mail.mailers.microsoft-graph.tenant_id', ''),
                clientId: config('mail.mailers.microsoft-graph.client_id', ''),
                clientSecret: config('mail.mailers.microsoft-graph.client_secret', ''),
                accessTokenTtl: config('mail.mailers.microsoft-graph.access_token_ttl', 3000),
            );
        });

        Mail::extend('microsoft-graph', function (array $config) {
            throw_if(blank($config['from']['address'] ?? []), ConfigurationMissing::fromAddress());

            return new MicrosoftGraphTransport(
                $this->app->make(MicrosoftGraphApiService::class)
            );
        });
    }
}
