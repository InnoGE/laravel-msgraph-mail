<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InnoGE\LaravelMsGraphMail\Exceptions\ConfigurationMissing;
use InnoGE\LaravelMsGraphMail\Tests\Stubs\TestMail;

it('sends html mails with microsoft graph', function () {
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

    Cache::set('microsoft-graph-api-access-token', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')
        ->bcc('tim@innoge.de')
        ->cc('nuno@laravel.com')
        ->send(new TestMail());

    Http::assertSent(function (Request $value) {
        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail')
            ->hasHeader('Authorization', 'Bearer foo_access_token')->toBeTrue()
            ->body()->json()->toBe([
                'message' => [
                    'subject' => 'Dev Test',
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => "<b>Test</b>\n",
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'caleb@livewire.com',
                            ],
                        ],
                    ],
                    'ccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'nuno@laravel.com',
                            ],
                        ],
                    ],
                    'bccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'tim@innoge.de',
                            ],
                        ],
                    ],
                    'replyTo' => [],
                    'sender' => [
                        'emailAddress' => [
                            'address' => 'taylor@laravel.com',
                        ],
                    ],
                    'attachments' => [
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-1.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                        ],
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-2.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                        ],
                    ],
                ],
                'saveToSentItems' => false,
            ]);

        return true;
    });
});

it('sends text mails with microsoft graph', function () {
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

    Cache::set('microsoft-graph-api-access-token', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')
        ->bcc('tim@innoge.de')
        ->cc('nuno@laravel.com')
        ->send(new TestMail(false));

    Http::assertSent(function (Request $value) {
        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail')
            ->hasHeader('Authorization', 'Bearer foo_access_token')->toBeTrue()
            ->body()->json()->toBe([
                'message' => [
                    'subject' => 'Dev Test',
                    'body' => [
                        'contentType' => 'Text',
                        'content' => "Test\n",
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'caleb@livewire.com',
                            ],
                        ],
                    ],
                    'ccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'nuno@laravel.com',
                            ],
                        ],
                    ],
                    'bccRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => 'tim@innoge.de',
                            ],
                        ],
                    ],
                    'replyTo' => [],
                    'sender' => [
                        'emailAddress' => [
                            'address' => 'taylor@laravel.com',
                        ],
                    ],
                    'attachments' => [
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-1.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                        ],
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-2.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                        ],
                    ],
                ],
                'saveToSentItems' => false,
            ]);

        return true;
    });
});

it('creates an oauth access token', function () {
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

    Http::fake([
        'https://login.microsoftonline.com/foo_tenant_id/oauth2/v2.0/token' => Http::response(['access_token' => 'foo_access_token']),
        'https://graph.microsoft.com/v1.0*' => Http::response(['value' => []]),
    ]);

    Mail::to('caleb@livewire.com')
        ->send(new TestMail(false));

    Http::assertSent(function (Request $request) {
        if (Str::startsWith($request->url(), 'https://login.microsoftonline.com')) {
            expect($request)
                ->url()->toBe('https://login.microsoftonline.com/foo_tenant_id/oauth2/v2.0/token')
                ->isForm()->toBeTrue()
                ->body()->toBe('grant_type=client_credentials&client_id=foo_client_id&client_secret=foo_client_secret&scope=https%3A%2F%2Fgraph.microsoft.com%2F.default');
        }

        return true;
    });

    expect(Cache::get('microsoft-graph-api-access-token'))
        ->toBe('foo_access_token');
});

it('throws exceptions when config is missing', function (array $config, string $exceptionMessage) {
    Config::set('mail.mailers.microsoft-graph', $config);
    Config::set('mail.default', 'microsoft-graph');

    try {
        Mail::to('caleb@livewire.com')
            ->send(new TestMail(false));
    } catch (Exception $e) {
        expect($e)
            ->toBeInstanceOf(ConfigurationMissing::class)
            ->getMessage()->toBe($exceptionMessage);
    }
})->with(
    [
        [
            [
                'transport' => 'microsoft-graph',
                'client_id' => 'foo_client_id',
                'client_secret' => 'foo_client_secret',
                'tenant_id' => '',
                'from' => [
                    'address' => 'taylor@laravel.com',
                    'name' => 'Taylor Otwell',
                ],
            ],
            'The tenant id is missing from the configuration file.',
        ],
        [
            [
                'transport' => 'microsoft-graph',
                'client_id' => '',
                'client_secret' => 'foo_client_secret',
                'tenant_id' => 'foo_tenant_id',
                'from' => [
                    'address' => 'taylor@laravel.com',
                    'name' => 'Taylor Otwell',
                ],
            ],
            'The client id is missing from the configuration file.',
        ],
        [
            [
                'transport' => 'microsoft-graph',
                'client_id' => 'foo_client_id',
                'client_secret' => '',
                'tenant_id' => 'foo_tenant_id',
                'from' => [
                    'address' => 'taylor@laravel.com',
                    'name' => 'Taylor Otwell',
                ],
            ],
            'The client secret is missing from the configuration file.',
        ],
        [
            [
                'transport' => 'microsoft-graph',
                'client_id' => 'foo_client_id',
                'client_secret' => 'foo_client_secret',
                'tenant_id' => 'foo_tenant_id',
            ],
            'The mail from address is missing from the configuration file.',
        ],
    ]);
