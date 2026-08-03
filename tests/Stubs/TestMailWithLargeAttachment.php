<?php

namespace InnoGE\LaravelMsGraphMail\Tests\Stubs;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMailWithLargeAttachment extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $attachment,
        private readonly ?string $smallAttachment = null,
    ) {}

    public function envelope()
    {
        return new Envelope(
            subject: 'Dev Test',
        );
    }

    public function content()
    {
        return new Content(html: 'html-mail');
    }

    public function attachments(): array
    {
        return array_filter([
            Attachment::fromData(fn (): string => $this->attachment, 'large-file.bin')
                ->withMime('application/octet-stream'),
            $this->smallAttachment !== null
                ? Attachment::fromData(fn (): string => (string) $this->smallAttachment, 'small-file.bin')
                    ->withMime('application/octet-stream')
                : null,
        ]);
    }
}
