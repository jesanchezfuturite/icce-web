<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso a ventas. Responder el correo contesta directo al prospecto. */
class ContactMessageNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contactMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Contacto] {$this->contactMessage->subject} · {$this->contactMessage->name}",
            replyTo: [$this->contactMessage->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact-notification');
    }
}
