<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\CheckoutData;
use App\Actions\Checkout\OutOfStockException;
use App\Actions\Checkout\PlaceOrders;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderReceivedNotification;
use App\Models\Order;
use App\Models\User;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\PaymentResult;
use App\Rules\CardNumber;
use App\Support\Cart\Cart;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Checkout del carrito híbrido (3.5 / flujos 1 y 2).
 *
 * El pago sólo aparece si hay algo cobrable. Un carrito exclusivamente de
 * cotización pasa por el mismo formulario pero sin pedir tarjeta.
 *
 * Comprar o cotizar exige cuenta, pero se pide hasta este último paso: el
 * visitante arma su carrito libremente y, justo antes de pagar, crea su cuenta
 * (o inicia sesión) en el mismo formulario. Sin cuenta no habría dónde seguir
 * el pedido ni dónde pagar después una cotización autorizada.
 */
class CheckoutController extends Controller
{
    private const SESSION_PLACED = 'icce.checkout.placed';

    /** Tope por pedido en línea: los importes se guardan como decimal(10,2). */
    private const MAX_ONLINE_TOTAL = 10_000_000;

    public function __construct(
        private readonly Cart $cart,
        private readonly PaymentGateway $gateway,
    ) {}

    public function show(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('carrito')->with('aviso', 'Tu carrito está vacío.');
        }

        return view('pages.checkout.index', [
            'cart' => $this->cart,
            'purchasable' => $this->cart->purchasable(),
            'quotable' => $this->cart->quotable(),
            'methods' => $this->gateway->supportedMethods(),
            'gateway' => $this->gateway->name(),
            'checkoutToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, PlaceOrders $placeOrders): RedirectResponse
    {
        // Cada vez que se pinta el formulario nace un token. Es lo que convierte un
        // doble clic, un reenvío o dos pestañas en *un* pedido: el segundo envío
        // con el mismo token espera su turno y encuentra el pedido ya hecho.
        $request->validate(
            ['checkout_token' => ['required', 'uuid']],
            ['checkout_token.*' => 'El formulario expiró. Recarga la página e inténtalo de nuevo.'],
        );

        $token = $request->string('checkout_token')->value();
        $lock = Cache::lock("checkout:{$token}", 120);

        if (! $lock->get()) {
            return back()->withInput()->withErrors([
                'carrito' => 'Estamos procesando tu pedido. Espera unos segundos y revisa «Mi cuenta» antes de intentarlo de nuevo.',
            ]);
        }

        try {
            return $this->process($request, $placeOrders, $token);
        } finally {
            $lock->release();
        }
    }

    private function process(Request $request, PlaceOrders $placeOrders, string $token): RedirectResponse
    {
        $doneKey = "checkout:done:{$token}";

        if ($folios = Cache::get($doneKey)) {
            return redirect()->route('checkout.gracias')->with(self::SESSION_PLACED, $folios);
        }

        if ($this->cart->isEmpty()) {
            return redirect()->route('carrito')->with('aviso', 'Tu carrito está vacío.');
        }

        // Los importes caben en decimal(10,2): un total absurdo reventaría al guardar
        if ($this->cart->total() >= self::MAX_ONLINE_TOTAL) {
            return back()->withInput()->withErrors([
                'carrito' => 'El monto de este pedido es demasiado alto para procesarse en línea. Contacta a ventas para cotizarlo.',
            ]);
        }

        $needsPayment = $this->cart->purchasable()->isNotEmpty();
        $quotedIds = $this->cart->quotable()->map(fn ($line) => $line->product->id)->all();

        $guest = ! $request->user();

        if ($request->filled('email')) {
            $request->merge(['email' => mb_strtolower(trim($request->string('email')->value()))]);
        }

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191', $guest ? 'unique:users,email' : 'nullable'],
            'telefono' => ['required', 'string', 'max:50'],
            'empresa' => ['nullable', 'string', 'max:150'],
            'rfc' => ['nullable', 'string', 'max:20'],
            'calle' => ['required', 'string', 'max:191'],
            'colonia' => ['nullable', 'string', 'max:150'],
            'ciudad' => ['required', 'string', 'max:150'],
            'estado' => ['required', 'string', 'max:150'],
            'cp' => ['required', 'string', 'max:10'],
            'referencias' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'metodo_pago' => [$needsPayment ? 'required' : 'nullable', 'string', 'in:'.implode(',', array_keys($this->gateway->supportedMethods()))],
            'tarjeta' => $needsPayment && $request->input('metodo_pago') === 'card'
                ? ['required', 'string', 'max:25', new CardNumber]
                : ['nullable', 'string', 'max:25'],
            'password' => $guest ? ['required', 'confirmed', Password::min(8)->max(100)] : ['nullable'],
        ], messages: [
            'email.unique' => 'Ya existe una cuenta con este correo. Inicia sesión para continuar con tu compra.',
        ], attributes: [
            'nombre' => 'nombre', 'email' => 'correo electrónico', 'telefono' => 'teléfono',
            'calle' => 'calle y número', 'ciudad' => 'ciudad', 'estado' => 'estado', 'cp' => 'código postal',
            'metodo_pago' => 'método de pago', 'tarjeta' => 'número de tarjeta', 'password' => 'contraseña',
        ]);

        $data = CheckoutData::fromRequest($validated);

        // La cuenta nace en la misma transacción que las órdenes: si no hay
        // existencia no queda una cuenta a medias.
        try {
            $placed = $placeOrders($this->cart, $data, $request->user() ?? fn () => $this->register($validated));
        } catch (OutOfStockException $e) {
            return back()->withInput()->withErrors(['carrito' => $e->describe()]);
        } catch (LockTimeoutException) {
            return back()->withInput()->withErrors([
                'carrito' => 'Estamos recibiendo muchos pedidos en este momento. Inténtalo de nuevo en unos segundos.',
            ]);
        }

        if ($guest) {
            Auth::login($placed->user);
            $request->session()->regenerate();
        }

        // Cobro sólo de la parte vendible; la cotización nunca se cobra (REQ-02)
        if ($placed->sale !== null) {
            $result = $this->charge($placed->sale, $data->paymentPayload);

            if (! $result->successful) {
                $placeOrders->releaseStock($placed->sale);

                $placed->sale->update([
                    'status' => OrderStatus::Cancelled,
                    'cancelled_at' => now(),
                    'payment_provider' => $this->gateway->name(),
                    'payment_status' => 'failed',
                    'internal_notes' => $result->message,
                ]);

                $message = $result->message;

                // En un carrito mixto la cotización ya quedó registrada: sus líneas
                // salen del carrito para que el reintento no la duplique.
                if ($placed->quote !== null) {
                    $this->cart->removeMany($quotedIds);
                    $this->notify($placed->quote);
                    $message .= " Tu solicitud de cotización {$placed->quote->folio} sí quedó registrada.";
                }

                return back()->withInput()->withErrors(['pago' => $message]);
            }

            $this->applyPayment($placed->sale, $result->reference, $result->status);
        }

        foreach ($placed->all() as $order) {
            $this->notify($order);
        }

        $this->cart->clear();

        $folios = collect($placed->all())->pluck('folio')->all();
        Cache::put($doneKey, $folios, now()->addHours(2));

        return redirect()->route('checkout.gracias')->with(self::SESSION_PLACED, $folios);
    }

    /**
     * Una pasarela que lanza una excepción (esbozos sin implementar, red caída)
     * se trata como cobro fallido: la orden se cancela y la existencia vuelve,
     * en vez de dejar un pedido colgado con piezas apartadas y un error 500.
     *
     * @param  array<string, mixed>  $payload
     */
    private function charge(Order $order, array $payload): PaymentResult
    {
        try {
            return $this->gateway->charge($order, $payload);
        } catch (\Throwable $e) {
            Log::error('La pasarela falló al cobrar '.$order->folio, ['error' => $e->getMessage()]);

            return PaymentResult::failed('No pudimos procesar el pago en este momento. Inténtalo de nuevo en unos minutos o contacta a ventas.');
        }
    }

    /** Cuenta de cliente creada al comprar; el cast del modelo guarda la contraseña con hash. */
    private function register(array $validated): User
    {
        return User::create([
            'name' => $validated['nombre'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Client,
            'phone' => $validated['telefono'],
            'company' => $validated['empresa'] ?? null,
            'rfc' => $validated['rfc'] ?? null,
            'is_active' => true,
        ]);
    }

    public function gracias(Request $request): View|RedirectResponse
    {
        $folios = $request->session()->get(self::SESSION_PLACED, []);

        if ($folios === []) {
            return redirect()->route('catalogo.index');
        }

        return view('pages.checkout.gracias', [
            'orders' => Order::with('items')->whereIn('folio', $folios)->get(),
        ]);
    }

    /** Marca la venta como pagada (o en espera de SPEI) y deja bitácora. */
    private function applyPayment(Order $order, ?string $reference, ?string $status): void
    {
        $paid = $status === 'paid';

        $order->update([
            'status' => $paid ? OrderStatus::Paid : OrderStatus::Pending,
            'paid_at' => $paid ? now() : null,
            'payment_provider' => $this->gateway->name(),
            'payment_reference' => $reference,
            'payment_status' => $status,
        ]);

        if ($paid) {
            $order->statusHistories()->create([
                'from_status' => OrderStatus::Pending,
                'to_status' => OrderStatus::Paid,
                'note' => 'Pago confirmado por la pasarela ('.$this->gateway->name().').',
                'notified_customer' => true,
            ]);
        }
    }

    /**
     * Correos transaccionales. Un fallo del servidor de correo no debe tumbar
     * un pedido ya cobrado: se registra y el flujo continúa.
     */
    private function notify(Order $order): void
    {
        try {
            Mail::to($order->customer_email)->send(new OrderPlacedMail($order));
            Mail::to(config('icce.sales_email'))->send(new OrderReceivedNotification($order));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de la orden '.$order->folio, ['error' => $e->getMessage()]);
        }
    }
}
