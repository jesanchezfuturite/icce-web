<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Portal de cliente (7.0). Adelanto de la fase 5: historial de pedidos y
 * cotizaciones (7.1) y timeline de rastreo (7.2). Los datos de facturación
 * (7.3) y la aprobación de cotizaciones en línea llegan con esa fase.
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

        return view('pages.portal.pedido', [
            'order' => $order->load(['items.product', 'statusHistories.user', 'agent']),
        ]);
    }
}
