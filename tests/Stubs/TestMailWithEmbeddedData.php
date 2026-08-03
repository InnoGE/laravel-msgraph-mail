<?php

namespace InnoGE\LaravelMsGraphMail\Tests\Stubs;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMailWithEmbeddedData extends Mailable
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
        return new Content(html: 'html-mail-with-embed-data');
    }
}
