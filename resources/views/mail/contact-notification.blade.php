<x-mail::message>
# {{ $contactMessage->subject }}

**{{ $contactMessage->name }}**@if($contactMessage->company) · {{ $contactMessage->company }}@endif

**Correo:** {{ $contactMessage->email }}
**Teléfono:** {{ $contactMessage->phone }}
@if($contactMessage->location)
**Obra:** {{ $contactMessage->location }}
@endif

<x-mail::panel>
{{ $contactMessage->message }}
</x-mail::panel>

Puedes responder este correo directamente: la respuesta le llega al prospecto.

<x-mail::button :url="url('/admin/contact-messages')">
Abrir en el backoffice
</x-mail::button>
</x-mail::message>
