<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\RentalRequestStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\RentalRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Lo que el equipo necesita saber al abrir el backoffice: qué está esperando
 * a alguien. No métricas de vanidad, sino colas de trabajo.
 */
class OperacionOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $porCotizar = Order::query()
            ->where('order_type', OrderType::Quote)
            ->where('status', OrderStatus::Pending)
            ->count();

        $enProceso = Order::whereIn('status', [
            OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped,
        ])->count();

        $vencidas = Order::whereNotNull('estimated_delivery_date')
            ->whereDate('estimated_delivery_date', '<', now())
            ->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled])
            ->count();

        $stockBajo = Product::active()->lowStock()->count();

        $leads = RentalRequest::where('status', RentalRequestStatus::New)->count();

        return [
            Stat::make('Cotizaciones por atender', $porCotizar)
                ->description($porCotizar > 0 ? 'Esperan precio de un agente' : 'Todo atendido')
                ->color($porCotizar > 0 ? 'danger' : 'success')
                ->icon('heroicon-m-document-text'),

            Stat::make('Pedidos en proceso', $enProceso)
                ->description('Pagados, en almacén o en tránsito')
                ->color('warning')
                ->icon('heroicon-m-truck'),

            Stat::make('Entregas vencidas', $vencidas)
                ->description($vencidas > 0 ? 'Pasaron su fecha comprometida' : 'Ninguna atrasada')
                ->color($vencidas > 0 ? 'danger' : 'success')
                ->icon('heroicon-m-exclamation-triangle'),

            Stat::make('Productos con stock bajo', $stockBajo)
                ->description('Al o por debajo de su umbral')
                ->color($stockBajo > 0 ? 'warning' : 'success')
                ->icon('heroicon-m-archive-box'),

            Stat::make('Leads de renta nuevos', $leads)
                ->description($leads > 0 ? 'Sin contactar' : 'Todos contactados')
                ->color($leads > 0 ? 'danger' : 'success')
                ->icon('heroicon-m-wrench-screwdriver'),
        ];
    }
}
