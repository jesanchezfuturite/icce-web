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
                'nav.categories',
                now()->addHour(),
                fn () => Category::query()
                    ->roots()
                    ->active()
                    ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (Category $root) => [
                        'name' => $root->name,
                        'slug' => $root->slug,
                        'children' => $root->children
                            ->map(fn (Category $child) => ['name' => $child->name, 'slug' => $child->slug])
                            ->all(),
                    ])
                    ->all(),
            ));
        });
    }
}
