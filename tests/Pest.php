<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use InnoGE\LaravelMsGraphMail\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function configureMicrosoftGraphMailer(): void
{
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
    Config::set('mail.default', 'microsoft-graph');
    Config::set('filesystems.default', 'local');
    Config::set('filesystems.disks.local.root', realpath(__DIR__.'/Resources/files'));

    Cache::set('microsoft-graph-api-access-token-foo_tenant_id-foo_client_id', 'foo_access_token', 3600);
}
