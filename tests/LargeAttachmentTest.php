<?php

use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InnoGE\LaravelMsGraphMail\Exceptions\MissingMailReadWritePermissionException;
use InnoGE\LaravelMsGraphMail\Tests\Stubs\TestMailWithLargeAttachment;

function fakeDraftEndpoints(): void
{
    Http::fake([
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/attachments/createUploadSession' => Http::response(['uploadUrl' => 'https://upload.example.com/session-1']),
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/attachments' => Http::response(['id' => 'attachment-id'], 201),
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/send' => Http::response(null, 202),
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/sent-id/permanentDelete' => Http::response(null, 204),
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages?*' => Http::response(['value' => [
            ['id' => 'draft-id', 'isDraft' => true],
            ['id' => 'sent-id', 'isDraft' => false],
        ]]),
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages' => Http::response(['id' => 'draft-id', 'internetMessageId' => '<message-id@innoge.de>'], 201),
        'https://upload.example.com/*' => Http::response(['id' => 'attachment-id'], 201),
    ]);
}

function sentRequestUrls(): array
{
    return Http::recorded()
        ->map(fn (array $pair) => "{$pair[0]->method()} {$pair[0]->url()}")
        ->all();
}

it('sends large mails via a draft with an upload session', function () {
    configureMicrosoftGraphMailer();
    fakeDraftEndpoints();

    $large = Str::repeat('L', 4_000_000);
    $small = Str::repeat('S', 1_000_000);

    Mail::to('caleb@livewire.com')->send(new TestMailWithLargeAttachment($large, $small));

    $requests = Http::recorded()->map(fn (array $pair) => $pair[0])->all();
    $urls = sentRequestUrls();

    expect($urls)->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages')
        ->and($urls)->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/attachments/createUploadSession')
        ->and($urls)->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/attachments')
        ->and($urls)->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/send')
        ->and($urls)->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/sent-id/permanentDelete')
        ->and($urls)->not->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail');

    expect(array_search('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/sent-id/permanentDelete', $urls))
        ->toBeGreaterThan(array_search('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/send', $urls));

    foreach ($requests as $request) {
        if (Str::endsWith($request->url(), '/messages') && $request->method() === 'POST') {
            $draft = json_decode($request->body(), true);
            expect($draft)->not->toHaveKey('attachments')
                ->and($draft['subject'])->toBe('Dev Test');
        }

        if (Str::endsWith($request->url(), '/createUploadSession')) {
            $item = json_decode($request->body(), true)['AttachmentItem'];
            expect($item['attachmentType'])->toBe('file')
                ->and($item['name'])->toBe('large-file.bin')
                ->and($item['size'])->toBe(4_000_000);
        }

        if (Str::endsWith($request->url(), '/draft-id/attachments') && $request->method() === 'POST') {
            $attachment = json_decode($request->body(), true);
            expect($attachment['name'])->toBe('small-file.bin')
                ->and($attachment['contentBytes'])->toBe(base64_encode(Str::repeat('S', 1_000_000)));
        }
    }

    $uploads = array_values(array_filter($requests, fn (Request $request) => Str::startsWith($request->url(), 'https://upload.example.com')));
    expect($uploads)->toHaveCount(2)
        ->and($uploads[0]->header('Content-Range')[0])->toBe('bytes 0-3276799/4000000')
        ->and($uploads[1]->header('Content-Range')[0])->toBe('bytes 3276800-3999999/4000000')
        ->and($uploads[0]->hasHeader('Authorization'))->toBeFalse()
        ->and(strlen($uploads[0]->body()) + strlen($uploads[1]->body()))->toBe(4_000_000);
});

it('keeps using sendMail for small mails', function () {
    configureMicrosoftGraphMailer();
    Http::fake();

    Mail::to('caleb@livewire.com')->send(new TestMailWithLargeAttachment(attachment: 'tiny', smallAttachment: 'tiny-too'));

    Http::assertSent(fn (Request $request) => Str::endsWith($request->url(), '/sendMail'));
    Http::assertNotSent(fn (Request $request) => Str::endsWith($request->url(), '/messages'));
});

it('keeps the sent message in Sent Items when save_to_sent_items is enabled', function () {
    configureMicrosoftGraphMailer();
    Config::set('mail.mailers.microsoft-graph.save_to_sent_items', true);
    fakeDraftEndpoints();

    Mail::to('caleb@livewire.com')->send(new TestMailWithLargeAttachment(Str::repeat('L', 4_000_000)));

    $urls = sentRequestUrls();

    expect($urls)->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/send')
        ->and(collect($urls)->contains(fn (string $url) => Str::contains($url, 'permanentDelete')))->toBeFalse();
});

it('deletes the orphaned draft when an attachment upload fails', function () {
    configureMicrosoftGraphMailer();

    Http::fake([
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/attachments/createUploadSession' => Http::response(['error' => ['code' => 'ErrorInternalServerError']], 500),
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/permanentDelete' => Http::response(null, 204),
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages' => Http::response(['id' => 'draft-id', 'internetMessageId' => '<message-id@innoge.de>'], 201),
    ]);

    expect(fn () => Mail::to('caleb@livewire.com')->send(new TestMailWithLargeAttachment(Str::repeat('L', 4_000_000))))
        ->toThrow(RequestException::class);

    expect(sentRequestUrls())->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/permanentDelete')
        ->and(sentRequestUrls())->not->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/send');
});

it('explains the missing Mail.ReadWrite permission on 403 draft failures', function () {
    configureMicrosoftGraphMailer();

    Http::fake([
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages' => Http::response(['error' => ['code' => 'ErrorAccessDenied']], 403),
    ]);

    try {
        Mail::to('caleb@livewire.com')->send(new TestMailWithLargeAttachment(Str::repeat('L', 4_000_000)));
        $this->fail('Expected MissingMailReadWritePermissionException to be thrown.');
    } catch (MissingMailReadWritePermissionException $exception) {
        expect($exception->getMessage())->toContain('Mail.ReadWrite')
            ->and($exception->getPrevious())->toBeInstanceOf(RequestException::class);
    }
});
