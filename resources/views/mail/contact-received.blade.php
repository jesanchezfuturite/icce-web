<x-mail::message>
# Recibimos tu mensaje

Hola {{ \App\Support\PersonName::first($contactMessage->name) }},

Gracias por escribirnos. Un asesor revisa tu mensaje sobre
**{{ mb_strtolower($contactMessage->subject) }}** y te contacta el mismo día hábil.

<x-mail::panel>
{{ $contactMessage->message }}
</x-mail::panel>

Si tu asunto es urgente, escríbenos por WhatsApp o llama al 81 8100 0000.

Gracias,<br>
**ICCE Rentas y Servicios**
</x-mail::message>
