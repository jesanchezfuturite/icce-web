<?php

namespace App\Mail;

use App\Models\RentalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso a ventas de un lead de renta nuevo (flujo 3 del AppFlow). */
class RentalRequestNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RentalRequest $rentalRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Renta] {$this->rentalRequest->folio} · {$this->rentalRequest->equipment_name}",
            replyTo: [$this->rentalRequest->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.rental-notification');
    }
}
