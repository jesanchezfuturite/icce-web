<?php

namespace App\Mail;

use App\Models\RentalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Acuse al prospecto, con su folio y los requisitos por adelantado. */
class RentalRequestReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RentalRequest $rentalRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Recibimos tu solicitud de renta {$this->rentalRequest->folio}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.rental-received');
    }
}
