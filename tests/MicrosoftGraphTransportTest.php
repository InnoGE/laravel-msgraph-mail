<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('sends mails with microsoft graph', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
    ]);
    Http::fake();

    Http::assertSent(function ($value) {
        dd($value);
    });
});
