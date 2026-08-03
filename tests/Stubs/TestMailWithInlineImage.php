<?php

namespace InnoGE\LaravelMsGraphMail\Tests\Stubs;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMailWithInlineImage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(private readonly bool $isHtml = true) {}

    public function envelope()
    {
        return new Envelope(
            subject: 'Dev Test',
        );
    }

    public function content()
    {
        if (! $this->isHtml) {
            return new Content(text: 'text-mail');
        }

        return new Content(html: 'html-mail-with-inline-image');
    }
}
