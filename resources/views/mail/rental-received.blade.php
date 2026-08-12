<x-mail::message>
# Recibimos tu solicitud

Hola {{ \App\Support\PersonName::first($rentalRequest->client_name) }},

Registramos tu solicitud de renta con el folio **{{ $rentalRequest->folio }}**.

<x-mail::panel>
**{{ $rentalRequest->equipment_name }}**
{{ $rentalRequest->location }}@if($rentalRequest->start_date) · a partir del {{ $rentalRequest->start_date->translatedFormat('d \d\e F') }}@endif
@if($rentalRequest->rental_days) · {{ $rentalRequest->rental_days }} días @endif
</x-mail::panel>

Un agente confirma disponibilidad para esas fechas y te contacta con la tarifa
el mismo día hábil. Esta solicitud no genera ningún cobro.

Para agilizar la entrega, ve juntando la documentación que pedimos:

<x-mail::button :url="url('/renta/requisitos')">
Ver requisitos de renta
</x-mail::button>

Gracias,<br>
**ICCE Rentas y Servicios**
</x-mail::message>
