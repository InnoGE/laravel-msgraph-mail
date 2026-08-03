<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use InnoGE\LaravelMsGraphMail\Tests\Stubs\TestMail;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Email;

function assertSaveToSentItems(bool $expected): void
{
    Http::assertSent(function (Request $value) use ($expected) {
        expect(json_decode($value->body(), true)['saveToSentItems'])->toBe($expected);

        return true;
    });
}

it('honors save_to_sent_items on a mailer registered under a custom key', function () {
    // Regression: the flag used to be read from the hardcoded
    // mail.mailers.microsoft-graph key, silently breaking aliased mailers.
    Config::set('mail.mailers.ms-graph-alias', [
        'transport' => 'microsoft-graph',
        'client_id' => 'foo_client_id',
        'client_secret' => 'foo_client_secret',
        'tenant_id' => 'foo_tenant_id',
        'from' => [
            'address' => 'taylor@laravel.com',
            'name' => 'Taylor Otwell',
        ],
        'save_to_sent_items' => true,
    ]);
    Config::set('mail.default', 'ms-graph-alias');

    Cache::set('microsoft-graph-api-access-token-foo_tenant_id-foo_client_id', 'foo_access_token', 3600);

    Http::fake();

    Mail::to('caleb@livewire.com')->send(new TestMail);

    assertSaveToSentItems(true);
});

it('allows a mailable to override save_to_sent_items via metadata', function (bool $configured, bool $override) {
    configureMicrosoftGraphMailer();
    Config::set('mail.mailers.microsoft-graph.save_to_sent_items', $configured);

    Http::fake();

    $mail = (new TestMail)->withSymfonyMessage(function (Email $message) use ($override) {
        $message->getHeaders()->add(new MetadataHeader('save-to-sent-items', $override ? 'true' : 'false'));
    });

    Mail::to('caleb@livewire.com')->send($mail);

    assertSaveToSentItems($override);
})->with([
    'override on' => [false, true],
    'override off' => [true, false],
]);

it('does not forward metadata headers as internet message headers', function () {
    configureMicrosoftGraphMailer();

    Http::fake();

    $mail = (new TestMail)->withSymfonyMessage(function (Email $message) {
        $message->getHeaders()->add(new MetadataHeader('save-to-sent-items', 'true'));
    });

    Mail::to('caleb@livewire.com')->send($mail);

    Http::assertSent(function (Request $value) {
        expect(json_decode($value->body(), true)['message'])->not->toHaveKey('internetMessageHeaders');

        return true;
    });
});
