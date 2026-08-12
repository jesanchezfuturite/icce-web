<?php

namespace App\Mail;

use App\Enums\OrderType;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmación al cliente: compra directa o acuse de cotización según el tipo.
 * Un solo mailable porque el contenido comparte estructura y sólo cambia el
 * mensaje de qué sigue.
 */
class OrderPlacedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->order->order_type === OrderType::Quote
                ? "Recibimos tu solicitud de cotización {$this->order->folio}"
                : "Confirmación de tu pedido {$this->order->folio}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-placed',
            with: [
                'order' => $this->order->loadMissing('items'),
                'isQuote' => $this->order->order_type === OrderType::Quote,
            ],
        );
    }
}
