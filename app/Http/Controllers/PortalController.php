<?php

namespace App\Http\Controllers;

use App\Actions\Orders\PayQuote;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Payments\Contracts\PaymentGateway;
use App\Rules\CardNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Portal de cliente (7.0). Adelanto de la fase 5: historial de pedidos y
 * cotizaciones (7.1), timeline de rastreo (7.2) y pago de una cotización que
 * un agente autorizó. Los datos de facturación (7.3) llegan con esa fase.
 */
class PortalController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()->orders()
            ->with('items')
            ->latest()
            ->get();

        return view('pages.portal.index', [
            'orders' => $orders,
            'sales' => $orders->where('order_type', OrderType::DirectSale),
            'quotes' => $orders->where('order_type', OrderType::Quote),
            'inTransit' => $orders->whereIn('status', [OrderStatus::Processing, OrderStatus::Shipped]),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        // El folio es la clave de ruta, pero la orden debe ser del usuario
        abort_unless($order->user_id === $request->user()->id, 404);

        $gateway = app(PaymentGateway::class);

        return view('pages.portal.pedido', [
            'order' => $order->load(['items.product', 'statusHistories.user', 'agent']),
            'payable' => $order->canBePaidOnline(),
            'methods' => $gateway->supportedMethods(),
            'gateway' => $gateway->name(),
        ]);
    }

    /**
     * Pago de una cotización autorizada. Las reglas (autorizada, vigente, una
     * sola vez) viven en PayQuote; aquí sólo se valida el formulario.
     */
    public function pay(Request $request, Order $order, PayQuote $payQuote, PaymentGateway $gateway): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'metodo_pago' => ['required', 'string', 'in:'.implode(',', array_keys($gateway->supportedMethods()))],
            'tarjeta' => $request->input('metodo_pago') === 'card'
                ? ['required', 'string', 'max:25', new CardNumber]
                : ['nullable', 'string', 'max:25'],
        ], attributes: [
            'metodo_pago' => 'método de pago', 'tarjeta' => 'número de tarjeta',
        ]);

        $result = $payQuote($order, [
            'method' => $validated['metodo_pago'],
            'card_number' => preg_replace('/\s+/', '', $validated['tarjeta'] ?? ''),
        ], $request->user());

        if (! $result->successful) {
            return back()->withInput()->withErrors(['pago' => $result->message]);
        }

        return redirect()
            ->route('portal.pedido', $order)
            ->with('pago', $result->status === 'paid'
                ? '¡Pago recibido! Tu pedido avanza a almacén y te avisamos por correo.'
                : ($result->message ?? 'Registramos tu pago; avanzamos tu pedido en cuanto se acredite.'));
    }
}
