<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use InnoGE\LaravelMsGraphMail\Tests\Stubs\TestMailWithEmbeddedData;
use InnoGE\LaravelMsGraphMail\Tests\Stubs\TestMailWithNamedAttachments;

it('serializes named data and path attachments with explicit mime types', function () {
    configureMicrosoftGraphMailer();

    Http::fake();

    Mail::to('caleb@livewire.com')->send(new TestMailWithNamedAttachments);

    Http::assertSent(function (Request $value) {
        $attachments = json_decode($value->body(), true)['message']['attachments'];

        // Laravel serializes path attachments before data attachments,
        // regardless of their order in the attachments() array.
        expect($attachments)->toBe([
            [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => 'picture.jpg',
                'contentType' => 'image/jpeg',
                'contentBytes' => base64_encode((string) file_get_contents(__DIR__.'/Resources/files/blue.jpg')),
                'contentId' => 'picture.jpg',
                'isInline' => false,
            ],
            [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => 'report.csv',
                'contentType' => 'text/csv',
                'contentBytes' => base64_encode('raw file contents'),
                'contentId' => 'report.csv',
                'isInline' => false,
            ],
        ]);

        return true;
    });
});

it('serializes inline attachments embedded via embedData', function () {
    configureMicrosoftGraphMailer();

    Http::fake();

    Mail::to('caleb@livewire.com')->send(new TestMailWithEmbeddedData);

    Http::assertSent(function (Request $value) {
        $json = json_decode($value->body(), true);
        $attachment = $json['message']['attachments'][0];

        expect($json['message']['attachments'])->toHaveCount(1)
            ->and($attachment['@odata.type'])->toBe('#microsoft.graph.fileAttachment')
            ->and($attachment['contentType'])->toBe('image/jpeg')
            ->and($attachment['contentBytes'])->toBe(base64_encode((string) file_get_contents(__DIR__.'/Resources/files/blue.jpg')))
            ->and($attachment['isInline'])->toBeTrue()
            ->and($attachment['contentId'])->not->toBeEmpty()
            ->and($attachment['name'])->toBe('embedded-image.jpg')
            ->and($json['message']['body']['content'])->toContain('cid:'.$attachment['contentId']);

        return true;
    });
});
