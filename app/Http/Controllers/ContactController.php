<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessageNotification;
use App\Mail\ContactReceivedMail;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Formulario general de contacto (6.1).
 *
 * El mensaje se guarda primero y se envía después: si el servidor de correo
 * falla, el prospecto sigue en la bandeja del backoffice en vez de perderse.
 */
class ContactController extends Controller
{
    public function store(ContactRequest $request): RedirectResponse
    {
        $message = ContactMessage::create([
            'name' => $request->validated('nombre'),
            'company' => $request->validated('empresa'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('telefono'),
            'location' => $request->validated('obra'),
            'subject' => $request->validated('asunto'),
            'message' => $request->validated('mensaje'),
            'ip_address' => $request->ip(),
        ]);

        try {
            Mail::to(config('icce.sales_email'))->send(new ContactMessageNotification($message));
            Mail::to($message->email)->send(new ContactReceivedMail($message));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo del mensaje de contacto', [
                'mensaje' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        return back()->with('contacto.enviado', $message->name);
    }
}
