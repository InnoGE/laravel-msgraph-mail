<?php

namespace InnoGE\LaravelMsGraphMail\Tests\Stubs;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMailWithNamedAttachments extends Mailable
{
    use Queueable, SerializesModels;

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
        return [
            Attachment::fromData(fn (): string => 'raw file contents', 'report.csv')
                ->withMime('text/csv'),
            Attachment::fromPath(__DIR__.'/../Resources/files/blue.jpg')
                ->as('picture.jpg')
                ->withMime('image/jpeg'),
        ];
    }
}
