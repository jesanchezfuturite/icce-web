<?php

namespace App\Http\Controllers;

use App\Models\UrlRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Migración SEO del sitio anterior (TRD 4.3 / RNF-03).
 *
 * Se resuelve desde `Route::fallback()` y no desde un middleware global a
 * propósito: así el mapa sólo se consulta cuando ninguna ruta real coincidió.
 * El tráfico normal —que es todo el tráfico, una vez asentada la migración— no
 * paga ni una consulta por petición.
 */
class LegacyUrlController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $path = UrlRedirect::normalizePath($request->path());

        $redirect = UrlRedirect::query()
            ->where('is_active', true)
            ->where(function ($query) use ($path) {
                // Se prueba con y sin barra inicial: el mapa se sembró con
                // barra, pero una ruta capturada a mano puede venir sin ella.
                $query->where('old_path', $path)
                    ->orWhere('old_path', ltrim($path, '/'));
            })
            ->first();

        if ($redirect === null) {
            throw new NotFoundHttpException;
        }

        // Telemetría: saber qué rutas viejas siguen recibiendo visitas indica
        // qué enlaces externos hay que pedir que actualicen.
        $redirect->registerHit();

        if ($redirect->status_code === 410 || $redirect->new_path === null) {
            // 410 y no 404: le dice al buscador que la retire del índice en vez
            // de reintentarla. Es lo correcto para las páginas secuestradas.
            throw new GoneHttpException('Esta página fue retirada.');
        }

        return redirect()->to($redirect->new_path, $redirect->status_code);
    }
}
