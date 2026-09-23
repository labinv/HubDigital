<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class SystemMailTest extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Prueba de correo de HubDigital');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.system-mail-test');
    }
}
