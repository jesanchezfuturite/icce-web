<?php

namespace App\Exports;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Libro de Excel con los pedidos y cotizaciones de una consulta ya filtrada
 * (pestaña, filtros y búsqueda del listado del backoffice).
 *
 * Dos hojas: «Pedidos», una fila por orden, y «Partidas», una por producto.
 * Usa OpenSpout, que ya viene con Filament: se escribe en un archivo temporal,
 * sin cola de trabajos ni tablas nuevas.
 */
class OrdersExport
{
    private const PEDIDOS = [
        'Folio', 'Tipo', 'Estatus', 'Fecha del pedido',
        'Cliente', 'Empresa', 'Correo', 'Teléfono', 'Atiende',
        'Subtotal', 'Descuento', 'IVA', 'Envío', 'Total', 'Moneda',
        'Entrega estimada', 'Guía', 'Transporte', 'Vigencia de la cotización',
        'Cotizado el', 'Pagado el', 'Enviado el', 'Entregado el', 'Cancelado el',
        'Método de pago', 'Referencia de pago', 'Estatus del pago',
        'Calle y número', 'Colonia', 'C.P.', 'Ciudad', 'Estado', 'Referencias de la obra',
        'Partidas', 'Productos', 'Notas del cliente', 'Notas internas',
    ];

    private const PARTIDAS = [
        'Folio', 'Tipo', 'Estatus', 'Cliente', 'SKU', 'Producto', 'Cantidad',
        'Precio de lista', 'Precio cotizado', 'Precio aplicado', 'Importe', 'Notas',
    ];

    private Style $dinero;

    private Style $fechaHora;

    private Style $fecha;

    /** Escribe el libro en `$path` y devuelve cuántas órdenes incluyó. */
    public function write(Builder $query, string $path): int
    {
        $this->dinero = (new Style)->setFormat('#,##0.00');
        $this->fechaHora = (new Style)->setFormat('dd/mm/yyyy hh:mm');
        $this->fecha = (new Style)->setFormat('dd/mm/yyyy');

        $encabezado = (new Style)->setFontBold()->setBackgroundColor('E7F2D0');

        $writer = new Writer;
        $writer->openToFile($path);

        try {
            $writer->getCurrentSheet()->setName('Pedidos');
            $writer->addRow(Row::fromValues(self::PEDIDOS, $encabezado));

            $partidas = [];
            $total = 0;

            // Orden por id al final para que el paginado por bloques sea estable.
            $query->with(['agent', 'items'])->orderBy('orders.id')
                ->lazy(300)
                ->each(function (Order $order) use ($writer, &$partidas, &$total): void {
                    $writer->addRow($this->filaPedido($order));

                    foreach ($order->items as $item) {
                        $partidas[] = $this->filaPartida($order, $item);
                    }

                    $total++;
                });

            $writer->addNewSheetAndMakeItCurrent()->setName('Partidas');
            $writer->addRow(Row::fromValues(self::PARTIDAS, $encabezado));
            $writer->addRows($partidas);
        } finally {
            $writer->close();
        }

        return $total;
    }

    private function filaPedido(Order $order): Row
    {
        $direccion = $order->shipping_address ?? [];
        $productos = $order->items
            ->map(fn (OrderItem $item) => "{$item->quantity} × {$item->product_name}")
            ->implode('; ');

        return new Row([
            Cell::fromValue($order->folio),
            Cell::fromValue($order->order_type?->label()),
            Cell::fromValue($order->status?->label()),
            Cell::fromValue($order->created_at, $this->fechaHora),
            Cell::fromValue($order->customer_name),
            Cell::fromValue($order->customer_company),
            Cell::fromValue($order->customer_email),
            Cell::fromValue($order->customer_phone),
            Cell::fromValue($order->agent?->name),
            Cell::fromValue((float) $order->subtotal, $this->dinero),
            Cell::fromValue((float) $order->discount_amount, $this->dinero),
            Cell::fromValue((float) $order->tax_amount, $this->dinero),
            Cell::fromValue((float) $order->shipping_amount, $this->dinero),
            Cell::fromValue((float) $order->total_amount, $this->dinero),
            Cell::fromValue($order->currency),
            Cell::fromValue($order->estimated_delivery_date, $this->fecha),
            Cell::fromValue($order->tracking_number),
            Cell::fromValue($order->carrier),
            Cell::fromValue($order->quote_valid_until, $this->fecha),
            Cell::fromValue($order->quoted_at, $this->fechaHora),
            Cell::fromValue($order->paid_at, $this->fechaHora),
            Cell::fromValue($order->shipped_at, $this->fechaHora),
            Cell::fromValue($order->delivered_at, $this->fechaHora),
            Cell::fromValue($order->cancelled_at, $this->fechaHora),
            Cell::fromValue($order->payment_provider),
            Cell::fromValue($order->payment_reference),
            Cell::fromValue($order->payment_status),
            Cell::fromValue($direccion['calle'] ?? null),
            Cell::fromValue($direccion['colonia'] ?? null),
            Cell::fromValue($direccion['cp'] ?? null),
            Cell::fromValue($direccion['ciudad'] ?? null),
            Cell::fromValue($direccion['estado'] ?? null),
            Cell::fromValue($direccion['referencias'] ?? null),
            Cell::fromValue($order->items->count()),
            // Una celda de Excel admite 32,767 caracteres
            Cell::fromValue(mb_substr($productos, 0, 30000)),
            Cell::fromValue($order->customer_notes),
            Cell::fromValue($order->internal_notes),
        ]);
    }

    private function filaPartida(Order $order, OrderItem $item): Row
    {
        $aplicado = (float) ($item->quoted_unit_price ?? $item->unit_price);

        return new Row([
            Cell::fromValue($order->folio),
            Cell::fromValue($order->order_type?->label()),
            Cell::fromValue($order->status?->label()),
            Cell::fromValue($order->customer_name),
            Cell::fromValue($item->product_sku),
            Cell::fromValue($item->product_name),
            Cell::fromValue((int) $item->quantity),
            Cell::fromValue((float) $item->unit_price, $this->dinero),
            Cell::fromValue($item->quoted_unit_price !== null ? (float) $item->quoted_unit_price : null, $this->dinero),
            Cell::fromValue($aplicado, $this->dinero),
            Cell::fromValue((float) $item->line_total, $this->dinero),
            Cell::fromValue($item->notes),
        ]);
    }
}
