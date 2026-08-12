<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Acceso al portal de cliente (7.0). El backoffice tiene su propio login en
 * /admin, gestionado por Filament.
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function show(): View
    {
        return view('pages.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], attributes: [
            'email' => 'correo electrónico',
            'password' => 'contraseña',
        ]);

        // RNF-02: limita el rastreo de credenciales por correo + IP
        $key = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Vuelve a intentar en '
                    .RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Esta cuenta está desactivada. Contacta a tu agente de ventas.',
            ]);
        }

        // Al personal de ICCE le sirve más el backoffice que el portal de cliente
        return redirect()->intended($user->role->canAccessAdminPanel() ? '/admin' : route('portal.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
