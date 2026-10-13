<?php

namespace App\Providers;

use App\Models\Category;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Gateways\OpenpayGateway;
use App\Payments\Gateways\SimulatedGateway;
use App\Payments\Gateways\StripeGateway;
use App\Support\Cart\Cart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Uno por petición: el carrito cachea las líneas ya resueltas.
        $this->app->scoped(Cart::class, fn ($app) => new Cart($app['session.store']));

        $this->app->bind(PaymentGateway::class, function ($app) {
            $driver = config('icce.payment.driver');

            return $app->make(match ($driver) {
                'stripe' => StripeGateway::class,
                'openpay' => OpenpayGateway::class,
                default => SimulatedGateway::class,
            });
        });
    }

    public function boot(): void
    {
        // RNF-02: sin lazy loading accidental ni asignación masiva silenciosa
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        $this->shareNavigation();
    }

    /**
     * El árbol de categorías alimenta el menú del encabezado y del pie en todas
     * las vistas. Se cachea porque sólo cambia desde el backoffice.
     *
     * Se guarda como arreglo de primitivas a propósito: Laravel no deserializa
     * clases desde caché por omisión (config/cache.php: serializable_classes),
     * y guardar modelos de Eloquent exigiría relajar esa defensa.
     */
    private function shareNavigation(): void
    {
        View::composer(['components.site.header', 'components.site.footer'], function ($view) {
            $view->with('navCategories', Cache::remember(
                Category::NAV_CACHE_KEY,
                now()->addHour(),
                fn () => $this->navigationTree(),
            ));
        });
    }

    /**
     * Árbol de categorías activas para el menú, hasta MAX_DEPTH niveles, con una
     * sola consulta. Arreglos de primitivas: se cachea (ver config/cache.php).
     *
     * @return list<array{name: string, slug: string, children: list<array>}>
     */
    private function navigationTree(): array
    {
        $porPadre = Category::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'name', 'slug'])
            ->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        $construir = function (int $padreId, int $nivel) use (&$construir, $porPadre): array {
            if ($nivel > Category::MAX_DEPTH) {
                return [];
            }

            return ($porPadre[$padreId] ?? collect())
                ->map(fn (Category $category) => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'children' => $construir($category->id, $nivel + 1),
                ])
                ->values()
                ->all();
        };

        return $construir(0, 1);
    }
}
