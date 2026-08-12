<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Acuse al prospecto: saber que su mensaje llegó vale más que la respuesta rápida. */
class ContactReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contactMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recibimos tu mensaje · ICCE Rentas y Servicios');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.contact-received');
    }
}
