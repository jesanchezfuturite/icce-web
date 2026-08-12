<x-mail::message>
# {{ $order->order_type->label() }} {{ $order->folio }}

**Cliente:** {{ $order->customer_name }}
@if($order->customer_company)
**Empresa:** {{ $order->customer_company }}
@endif
**Correo:** {{ $order->customer_email }}
**Teléfono:** {{ $order->customer_phone }}

<x-mail::table>
| SKU | Producto | Cant. | Unitario | Importe |
|:----|:---------|:-----:|---------:|--------:|
@foreach($order->items as $item)
| {{ $item->product_sku }} | {{ $item->product_name }} | {{ $item->quantity }} | ${{ number_format((float) $item->unit_price, 2) }} | ${{ number_format((float) $item->line_total, 2) }} |
@endforeach
</x-mail::table>

**Total:** ${{ number_format((float) $order->total_amount, 2) }} MXN

@php $conMotivo = $order->items->whereNotNull('notes'); @endphp
@if($conMotivo->isNotEmpty())
**Por qué entró a cotización:**
@foreach($conMotivo as $item)
- {{ $item->product_name }} — {{ $item->notes }}
@endforeach
@endif

@if($order->shipping_address)
**Entrega:** {{ implode(', ', $order->shipping_address) }}
@endif

@if($order->customer_notes)
**Notas del cliente:** {{ $order->customer_notes }}
@endif

<x-mail::button :url="url('/admin')">
Abrir en el backoffice
</x-mail::button>
</x-mail::message>
