<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use InnoGE\LaravelMsGraphMail\Exceptions\MissingMailReadWritePermission;
use InnoGE\LaravelMsGraphMail\Tests\Stubs\TestMailWithLargeAttachment;

function fakeDraftEndpoints(): void
{
    Http::fake([
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/attachments/createUploadSession' => Http::response(['uploadUrl' => 'https://upload.example.com/session-1']),
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/attachments' => Http::response(['id' => 'attachment-id'], 201),
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/send' => Http::response(null, 202),
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages' => Http::response(['id' => 'draft-id'], 201),
        'https://upload.example.com/*' => Http::response(['id' => 'attachment-id'], 201),
    ]);
}

it('sends large mails via a draft with an upload session', function () {
    configureMicrosoftGraphMailer();
    fakeDraftEndpoints();

    // 4 MB attachment: above the sendMail cap and above the direct-attachment limit.
    $large = str_repeat('L', 4_000_000);
    // 1 MB attachment: small enough for a direct attachments POST.
    $small = str_repeat('S', 1_000_000);

    Mail::to('caleb@livewire.com')->send(new TestMailWithLargeAttachment($large, $small));

    $requests = [];
    Http::assertSent(function (Request $request) use (&$requests) {
        $requests[] = $request;

        return true;
    });

    $urls = array_map(fn (Request $request) => "{$request->method()} {$request->url()}", $requests);

    // Draft created without attachments, both attachments added separately, draft sent.
    expect($urls)->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages')
        ->and($urls)->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/attachments/createUploadSession')
        ->and($urls)->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/attachments')
        ->and($urls)->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages/draft-id/send')
        ->and($urls)->not->toContain('POST https://graph.microsoft.com/v1.0/users/taylor@laravel.com/sendMail');

    foreach ($requests as $request) {
        if (str_ends_with($request->url(), '/messages') && $request->method() === 'POST') {
            $draft = json_decode($request->body(), true);
            expect($draft)->not->toHaveKey('attachments')
                ->and($draft['subject'])->toBe('Dev Test');
        }

        if (str_ends_with($request->url(), '/createUploadSession')) {
            $item = json_decode($request->body(), true)['AttachmentItem'];
            expect($item['attachmentType'])->toBe('file')
                ->and($item['name'])->toBe('large-file.bin')
                ->and($item['size'])->toBe(4_000_000);
        }

        if (str_ends_with($request->url(), '/draft-id/attachments') && $request->method() === 'POST') {
            $attachment = json_decode($request->body(), true);
            expect($attachment['name'])->toBe('small-file.bin')
                ->and($attachment['contentBytes'])->toBe(base64_encode(str_repeat('S', 1_000_000)));
        }
    }

    // Chunked upload: 4 MB in 3.2 MB chunks => 2 PUTs with contiguous ranges.
    $uploads = array_values(array_filter($requests, fn (Request $request) => str_starts_with($request->url(), 'https://upload.example.com')));
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

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sendMail'));
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/messages'));
});

it('explains the missing Mail.ReadWrite permission on 403 draft failures', function () {
    configureMicrosoftGraphMailer();

    Http::fake([
        'https://graph.microsoft.com/v1.0/users/taylor@laravel.com/messages' => Http::response(['error' => ['code' => 'ErrorAccessDenied']], 403),
    ]);

    expect(fn () => Mail::to('caleb@livewire.com')->send(new TestMailWithLargeAttachment(str_repeat('L', 4_000_000))))
        ->toThrow(MissingMailReadWritePermission::class, 'Mail.ReadWrite');
});
