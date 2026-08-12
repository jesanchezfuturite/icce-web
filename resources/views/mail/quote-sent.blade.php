<x-mail::message>
# Tu cotización está lista

Hola {{ \App\Support\PersonName::first($order->customer_name) }},

Ajustamos los precios de tu solicitud **{{ $order->folio }}**.
Adjuntamos la propuesta en PDF.

@if($note)
> {{ $note }}
@endif

<x-mail::table>
| Producto | Cant. | Unitario | Importe |
|:---------|:-----:|---------:|--------:|
@foreach($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | ${{ number_format((float) $item->effectiveUnitPrice(), 2) }} | ${{ number_format((float) $item->line_total, 2) }} |
@endforeach
</x-mail::table>

**Subtotal:** ${{ number_format((float) $order->subtotal, 2) }} MXN
@if((float) $order->discount_amount > 0)
**Descuento:** −${{ number_format((float) $order->discount_amount, 2) }} MXN
@endif
**IVA:** ${{ number_format((float) $order->tax_amount, 2) }} MXN
**Total:** ${{ number_format((float) $order->total_amount, 2) }} MXN

@if($order->quote_valid_until)
Esta propuesta es válida hasta el **{{ $order->quote_valid_until->translatedFormat('d \d\e F, Y') }}**.
@endif

<x-mail::button :url="url('/portal')">
Revisar y aprobar
</x-mail::button>

¿Necesitas ajustar cantidades o fechas? Responde este correo y lo vemos.

Gracias,<br>
**ICCE Rentas y Servicios**
</x-mail::message>
