<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\CheckoutData;
use App\Actions\Checkout\OutOfStockException;
use App\Actions\Checkout\PlaceOrders;
use App\Enums\OrderStatus;
use App\Mail\OrderPlacedMail;
use App\Mail\OrderReceivedNotification;
use App\Models\Order;
use App\Payments\Contracts\PaymentGateway;
use App\Support\Cart\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Checkout del carrito híbrido (3.5 / flujos 1 y 2).
 *
 * El pago sólo aparece si hay algo cobrable. Un carrito exclusivamente de
 * cotización pasa por el mismo formulario pero sin pedir tarjeta.
 */
class CheckoutController extends Controller
{
    private const SESSION_PLACED = 'icce.checkout.placed';

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
        ]);
    }

    public function store(Request $request, PlaceOrders $placeOrders): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('carrito')->with('aviso', 'Tu carrito está vacío.');
        }

        $needsPayment = $this->cart->purchasable()->isNotEmpty();

        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191'],
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
            'tarjeta' => [$needsPayment && $request->input('metodo_pago') === 'card' ? 'required' : 'nullable', 'string', 'max:25'],
        ], attributes: [
            'nombre' => 'nombre', 'email' => 'correo electrónico', 'telefono' => 'teléfono',
            'calle' => 'calle y número', 'ciudad' => 'ciudad', 'estado' => 'estado', 'cp' => 'código postal',
            'metodo_pago' => 'método de pago', 'tarjeta' => 'número de tarjeta',
        ]);

        $data = CheckoutData::fromRequest($validated);

        try {
            $placed = $placeOrders($this->cart, $data, $request->user());
        } catch (OutOfStockException $e) {
            return back()->withInput()->withErrors(['carrito' => $e->describe()]);
        }

        // Cobro sólo de la parte vendible; la cotización nunca se cobra (REQ-02)
        if ($placed->sale !== null) {
            $result = $this->gateway->charge($placed->sale, $data->paymentPayload);

            if (! $result->successful) {
                $placeOrders->releaseStock($placed->sale);

                $placed->sale->update([
                    'status' => OrderStatus::Cancelled,
                    'cancelled_at' => now(),
                    'payment_provider' => $this->gateway->name(),
                    'payment_status' => 'failed',
                    'internal_notes' => $result->message,
                ]);

                return back()->withInput()->withErrors(['pago' => $result->message]);
            }

            $this->applyPayment($placed->sale, $result->reference, $result->status);
        }

        foreach ($placed->all() as $order) {
            $this->notify($order);
        }

        $this->cart->clear();

        return redirect()
            ->route('checkout.gracias')
            ->with(self::SESSION_PLACED, collect($placed->all())->pluck('folio')->all());
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
