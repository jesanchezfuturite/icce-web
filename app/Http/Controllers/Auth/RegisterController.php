<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Registro de cliente (7.0). Es la misma cuenta que el checkout crea al comprar,
 * pero abierta desde el login para quien quiere ver el catálogo y su portal antes
 * de pedir algo. Siempre es rol cliente: el personal de ICCE se da de alta desde
 * el backoffice.
 */
class RegisterController extends Controller
{
    public function show(): View
    {
        return view('pages.auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Mismo criterio que el checkout: el correo se guarda en minúsculas para
        // que «Ana@Obra.mx» y «ana@obra.mx» no sean dos cuentas.
        if ($request->filled('email')) {
            $request->merge(['email' => mb_strtolower(trim($request->string('email')->value()))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:150'],
            'password' => ['required', 'confirmed', Password::min(8)->max(100)],
        ], messages: [
            'email.unique' => 'Ya existe una cuenta con este correo. Inicia sesión.',
        ], attributes: [
            'name' => 'nombre', 'email' => 'correo electrónico', 'phone' => 'teléfono',
            'company' => 'empresa', 'password' => 'contraseña',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null,
            'role' => UserRole::Client,
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('catalogo.index'));
    }
}
