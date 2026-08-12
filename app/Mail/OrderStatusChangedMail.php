<?php

namespace App\Mail;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso al cliente cuando su pedido avanza en el timeline (REQ-04 / REQ-05). */
class OrderStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** `$previousStatus`, no `$from`: Mailable ya define esa propiedad. */
    public function __construct(
        public Order $order,
        public OrderStatus $previousStatus,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu pedido {$this->order->folio}: {$this->order->status->label()}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-status-changed',
            with: [
                'order' => $this->order,
                'note' => $this->note,
                'steps' => OrderStatus::trackingSteps(),
                'position' => $this->order->status->trackingPosition(),
            ],
        );
    }
}
