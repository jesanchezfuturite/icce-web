<x-mail::message>
# Tu pedido avanzó

Hola {{ \App\Support\PersonName::first($order->customer_name) }},

Tu pedido **{{ $order->folio }}** pasó a **{{ $order->status->label() }}**.

@if($position >= 0)
<x-mail::panel>
@foreach($steps as $i => $step)
{{ $i <= $position ? '●' : '○' }} {{ $step->label() }}@if(! $loop->last) &nbsp;—&nbsp; @endif
@endforeach
</x-mail::panel>
@endif

@if($order->estimated_delivery_date)
**Fecha estimada de entrega:** {{ $order->estimated_delivery_date->translatedFormat('d \d\e F, Y') }}
@endif

@if($order->tracking_number)
**Guía de rastreo:** {{ $order->tracking_number }}@if($order->carrier) ({{ $order->carrier }})@endif
@endif

@if($note)
> {{ $note }}
@endif

@if($order->canBePaidOnline())
Tu cotización está autorizada: puedes pagarla desde tu cuenta con el precio acordado, **una sola vez**@if($order->quote_valid_until) y a más tardar el **{{ $order->quote_valid_until->translatedFormat('d \d\e F, Y') }}**@endif.

<x-mail::button :url="route('portal.pedido', $order)">
Pagar mi cotización
</x-mail::button>
@else
<x-mail::button :url="url('/portal')">
Ver el detalle en mi cuenta
</x-mail::button>
@endif

Gracias,<br>
**ICCE Rentas y Servicios**
</x-mail::message>
