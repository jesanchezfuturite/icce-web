<?php

namespace App\Mail;

use App\Actions\Orders\SendQuote;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Cotización ajustada, con el PDF adjunto y el enlace de pago (REQ-09). */
class QuoteSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu cotización {$this->order->folio} está lista",
            replyTo: array_filter([$this->order->agent?->email]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.quote-sent',
            with: ['order' => $this->order->loadMissing('items'), 'note' => $this->note],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn () => app(SendQuote::class)->pdf($this->order)->output(),
                "Cotizacion-{$this->order->folio}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
