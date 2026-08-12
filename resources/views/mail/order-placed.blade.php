<x-mail::message>
# {{ $isQuote ? '¡Recibimos tu solicitud!' : '¡Gracias por tu compra!' }}

Hola {{ \App\Support\PersonName::first($order->customer_name) }},

@if($isQuote)
Registramos tu solicitud de cotización con el folio **{{ $order->folio }}**.
Un agente la revisa, ajusta el precio por volumen y te envía la propuesta.
Normalmente respondemos el mismo día hábil.
@else
Tu pedido **{{ $order->folio }}** quedó registrado. En cuanto salga de almacén
te compartimos la fecha estimada de entrega y el número de guía.
@endif

<x-mail::table>
| Producto | Cant. | Importe |
|:---------|:-----:|--------:|
@foreach($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | ${{ number_format((float) $item->line_total, 2) }} |
@endforeach
</x-mail::table>

**Subtotal:** ${{ number_format((float) $order->subtotal, 2) }} MXN
**IVA:** ${{ number_format((float) $order->tax_amount, 2) }} MXN
**{{ $isQuote ? 'Total estimado' : 'Total' }}:** ${{ number_format((float) $order->total_amount, 2) }} MXN

@if($isQuote)
El total es indicativo: puede bajar con el descuento de volumen que aplique tu agente.
@endif

<x-mail::button :url="url('/portal')">
Ver el estatus en mi cuenta
</x-mail::button>

¿Alguna duda? Responde este correo o escríbenos a {{ config('icce.sales_email') }}.

Gracias,<br>
**ICCE Rentas y Servicios**
</x-mail::message>
