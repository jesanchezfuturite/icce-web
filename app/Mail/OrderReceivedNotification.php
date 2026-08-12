<?php

namespace App\Mail;

use App\Enums\OrderType;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso interno a ventas y almacén (flujo 1: "Notificación a Almacén"). */
class OrderReceivedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $tipo = $this->order->order_type === OrderType::Quote ? 'Nueva cotización' : 'Nuevo pedido';

        return new Envelope(
            subject: "[{$tipo}] {$this->order->folio} · {$this->order->customer_name}",
            replyTo: [$this->order->customer_email],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-received',
            with: ['order' => $this->order->loadMissing('items')],
        );
    }
}
