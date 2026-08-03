<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use InnoGE\LaravelMsGraphMail\Exceptions\ConfigurationMissing;
use InnoGE\LaravelMsGraphMail\Tests\Stubs\TestMail;

it('falls back to the global mail.from address when the mailer defines none', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
    ]);
    Config::set('mail.default', 'microsoft-graph');
    Config::set('mail.from', [
        'address' => 'global@laravel.com',
        'name' => 'Global Sender',
    ]);

    Cache::set('microsoft-graph-api-access-token-foo_tenant_id-foo_client_id', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')->send(new TestMail);

    Http::assertSent(function (Request $value) {
        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/global@laravel.com/sendMail');

        expect(json_decode($value->body(), true)['message']['sender'])->toBe([
            'emailAddress' => [
                'address' => 'global@laravel.com',
            ],
        ]);

        return true;
    });
});

it('rejects a present but empty mailer-level from even when a global from exists', function () {
    // mail.from only applies when the `from` key is omitted entirely;
    // present-but-empty must fail fast here.
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => null,
            'name' => null,
        ],
    ]);
    Config::set('mail.default', 'microsoft-graph');
    Config::set('mail.from', [
        'address' => 'global@laravel.com',
        'name' => 'Global Sender',
    ]);

    expect(fn () => Mail::to('caleb@livewire.com')->send(new TestMail(false)))
        ->toThrow(ConfigurationMissing::class, 'Configuration key from.address for microsoft-graph mailer is missing.');
});

it('prefers the mailer-level from address over the global one', function () {
    configureMicrosoftGraphMailer();
    Config::set('mail.from', [
        'address' => 'global@laravel.com',
        'name' => 'Global Sender',
    ]);

    Http::fake();

    Mail::to('caleb@livewire.com')->send(new TestMail);

    Http::assertSent(fn (Request $value) => $value->url() === 'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail');
});
