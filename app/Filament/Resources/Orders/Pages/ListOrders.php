<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** Sin acción de crear: las órdenes nacen del checkout, no del backoffice. */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Las pestañas siguen el orden de trabajo real del equipo: primero lo que
     * está esperando a alguien, después lo que ya está en movimiento.
     *
     * Nota: el parámetro de los closures debe llamarse `$query`. Filament
     * inyecta por nombre y, si no lo reconoce, intenta resolver el tipo desde
     * el contenedor —lo que con Eloquent\Builder revienta sin modelo.
     */
    public function getTabs(): array
    {
        $enProceso = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped];

        return [
            'por_atender' => Tab::make('Por atender')
                ->badge(fn () => $this->countFor(fn (Builder $q) => $q->where('status', OrderStatus::Pending)))
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Pending)),

            'cotizaciones' => Tab::make('Cotizaciones')
                ->badge(fn () => $this->countFor(fn (Builder $q) => $q->where('order_type', OrderType::Quote)))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('order_type', OrderType::Quote)),

            'en_proceso' => Tab::make('En proceso')
                ->badge(fn () => $this->countFor(fn (Builder $q) => $q->whereIn('status', $enProceso)))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $enProceso)),

            'entregados' => Tab::make('Entregados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Delivered)),

            'todos' => Tab::make('Todos'),
        ];
    }

    private function countFor(callable $filter): int
    {
        return $filter(static::getResource()::getEloquentQuery())->count();
    }
}
