<x-mail::message>
# Solicitud de renta {{ $rentalRequest->folio }}

**Equipo:** {{ $rentalRequest->equipment_name }}
**Cobertura:** {{ $rentalRequest->coverage?->label() }}

**Contacto:** {{ $rentalRequest->client_name }}@if($rentalRequest->company) · {{ $rentalRequest->company }}@endif
**Correo:** {{ $rentalRequest->email }}
**Teléfono:** {{ $rentalRequest->phone }}

**Obra:** {{ $rentalRequest->location }}
@if($rentalRequest->start_date)
**Inicio:** {{ $rentalRequest->start_date->translatedFormat('d \d\e F, Y') }}
@endif
@if($rentalRequest->rental_days)
**Días estimados:** {{ $rentalRequest->rental_days }}
@endif

@if($rentalRequest->notes)
<x-mail::panel>
{{ $rentalRequest->notes }}
</x-mail::panel>
@endif

@if($rentalRequest->project_description)
**Sobre el proyecto:** {{ $rentalRequest->project_description }}
@endif

<x-mail::button :url="url('/admin/rental-requests')">
Atender en el backoffice
</x-mail::button>
</x-mail::message>
