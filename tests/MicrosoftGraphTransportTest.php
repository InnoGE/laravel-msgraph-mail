<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InnoGE\LaravelMsGraphMail\Exceptions\ConfigurationInvalid;
use InnoGE\LaravelMsGraphMail\Exceptions\ConfigurationMissing;
use InnoGE\LaravelMsGraphMail\Exceptions\InvalidResponse;
use InnoGE\LaravelMsGraphMail\Tests\Stubs\TestMail;
use InnoGE\LaravelMsGraphMail\Tests\Stubs\TestMailWithInlineImage;

it('sends html mails with microsoft graph', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'save_to_sent_items' => null,
    ]);
    Config::set('mail.default', 'microsoft-graph');
    Config::set('mail.from', [
        'address' => 'taylor@laravel.com',
        'name' => 'Taylor Otwell',
    ]);

    Cache::set('microsoft-graph-api-access-token', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')
        ->bcc('tim@innoge.de')
        ->cc('nuno@laravel.com')
        ->send(new TestMail);

    Http::assertSent(function (Request $value) {
        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail')
            ->hasHeader('Authorization', 'Bearer foo_access_token')->toBeTrue()
            ->body()->json()->toBe([
                'message' => [
                    'subject' => 'Dev Test',
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => '<b>Test</b>'.PHP_EOL,
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
                            'contentId' => 'test-file-1.txt',
                            'isInline' => false,
                        ],
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-2.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                            'contentId' => 'test-file-2.txt',
                            'isInline' => false,
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
    ]);
    Config::set('mail.default', 'microsoft-graph');
    Config::set('mail.from', [
        'address' => 'taylor@laravel.com',
        'name' => 'Taylor Otwell',
    ]);

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
                        'content' => 'Test'.PHP_EOL,
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
                            'contentId' => 'test-file-1.txt',
                            'isInline' => false,
                        ],
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-2.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                            'contentId' => 'test-file-2.txt',
                            'isInline' => false,
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

it('throws exceptions on invalid access token in response', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Http::fake([
        'https://login.microsoftonline.com/foo_tenant_id/oauth2/v2.0/token' => Http::response(['access_token' => 123]),
    ]);

    expect(fn () => Mail::to('caleb@livewire.com')->send(new TestMail(false)))
        ->toThrow(InvalidResponse::class, 'Expected response to contain key access_token of type string, got: 123.');
});

it('throws exceptions when config is invalid', function (array $config, Exception $exception) {
    Config::set('mail.mailers.microsoft-graph', $config);
    Config::set('mail.default', 'microsoft-graph');

    expect(fn () => Mail::to('caleb@livewire.com')->send(new TestMail(false)))
        ->toThrow($exception);
})->with([
    [
        [
            'transport' => 'microsoft-graph',
            'client_id' => 'foo_client_id',
            'client_secret' => 'foo_client_secret',
        ],
        new ConfigurationMissing('tenant_id'),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 123,
            'client_id' => 'foo_client_id',
            'client_secret' => 'foo_client_secret',
        ],
        new ConfigurationInvalid('tenant_id', 123),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 'foo_tenant_id',
            'client_secret' => 'foo_client_secret',
        ],
        new ConfigurationMissing('client_id'),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 'foo_tenant_id',
            'client_id' => '',
            'client_secret' => 'foo_client_secret',
        ],
        new ConfigurationInvalid('client_id', ''),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 'foo_tenant_id',
            'client_id' => 'foo_client_id',
        ],
        new ConfigurationMissing('client_secret'),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 'foo_tenant_id',
            'client_id' => 'foo_client_id',
            'client_secret' => null,
        ],
        new ConfigurationInvalid('client_secret', null),
    ],
    [
        [
            'transport' => 'microsoft-graph',
            'tenant_id' => 'foo_tenant_id',
            'client_id' => 'foo_client_id',
            'client_secret' => 'foo_client_secret',
            'access_token_ttl' => false,
        ],
        new ConfigurationInvalid('access_token_ttl', false),
    ],
]);

it('sends html mails with inline images with microsoft graph', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
    ]);
    Config::set('mail.from', [
        'address' => 'taylor@laravel.com',
        'name' => 'Taylor Otwell',
    ]);
    Config::set('mail.default', 'microsoft-graph');
    Config::set('filesystems.default', 'local');
    Config::set('filesystems.disks.local.root', realpath(__DIR__.'/Resources/files'));

    Cache::set('microsoft-graph-api-access-token', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')
        ->bcc('tim@innoge.de')
        ->cc('nuno@laravel.com')
        ->send(new TestMailWithInlineImage);

    Http::assertSent(function (Request $value) {
        // ContentId gets random generated, so get this value first and check for equality later
        $inlineImageContentId = json_decode($value->body())->message->attachments[0]->contentId;

        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail')
            ->hasHeader('Authorization', 'Bearer foo_access_token')->toBeTrue()
            ->body()->json()->toBe([
                'message' => [
                    'subject' => 'Dev Test',
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => '<b>Test</b><img src="cid:'.$inlineImageContentId.'">'.PHP_EOL,
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
                            'name' => $inlineImageContentId,
                            'contentType' => 'image',
                            'contentBytes' => '/9j/4AAQSkZJRgABAQEASABIAAD//gATQ3JlYXRlZCB3aXRoIEdJTVD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQkFBQkUDQsNFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBT/wgARCABLAGQDAREAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAj/xAAWAQEBAQAAAAAAAAAAAAAAAAAABQj/2gAMAwEAAhADEAAAAZ71TDAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAABw/9oACAEBAAEFAgL/xAAUEQEAAAAAAAAAAAAAAAAAAABw/9oACAEDAQE/AQL/xAAUEQEAAAAAAAAAAAAAAAAAAABw/9oACAECAQE/AQL/xAAUEAEAAAAAAAAAAAAAAAAAAABw/9oACAEBAAY/AgL/xAAUEAEAAAAAAAAAAAAAAAAAAABw/9oACAEBAAE/IQL/2gAMAwEAAgADAAAAEEkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkv/xAAUEQEAAAAAAAAAAAAAAAAAAABw/9oACAEDAQE/EAL/xAAUEQEAAAAAAAAAAAAAAAAAAABw/9oACAECAQE/EAL/xAAUEAEAAAAAAAAAAAAAAAAAAABw/9oACAEBAAE/EAL/2Q==',
                            'contentId' => $inlineImageContentId,
                            'isInline' => true,
                        ],
                    ],
                ],
                'saveToSentItems' => false,
            ]);

        return true;
    });
});

test('the configured mail sender can be overwritten', function () {
    Config::set('mail.mailers.microsoft-graph', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
    ]);
    Config::set('mail.default', 'microsoft-graph');

    Cache::set('microsoft-graph-api-access-token', 'foo_access_token', 3600);

    Http::fake();

    $mailable = new TestMail(false);
    $mailable->from('other-mail@laravel.com', 'Other Mail');

    Mail::to('caleb@livewire.com')
        ->bcc('tim@innoge.de')
        ->cc('nuno@laravel.com')
        ->send($mailable);

    Http::assertSent(function (Request $value) {
        expect($value)
            ->url()->toBe('https://graph.microsoft.com/v1.0/users/other-mail@laravel.com/sendMail')
            ->hasHeader('Authorization', 'Bearer foo_access_token')->toBeTrue()
            ->body()->json()->toBe([
                'message' => [
                    'subject' => 'Dev Test',
                    'body' => [
                        'contentType' => 'Text',
                        'content' => 'Test'.PHP_EOL,
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
                            'address' => 'other-mail@laravel.com',
                        ],
                    ],
                    'attachments' => [
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-1.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                            'contentId' => 'test-file-1.txt',
                            'isInline' => false,
                        ],
                        [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => 'test-file-2.txt',
                            'contentType' => 'text',
                            'contentBytes' => 'Zm9vCg==',
                            'contentId' => 'test-file-2.txt',
                            'isInline' => false,
                        ],
                    ],
                ],
                'saveToSentItems' => false,
            ]);

        return true;
    });
});
