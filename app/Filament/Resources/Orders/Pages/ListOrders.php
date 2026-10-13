<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exports\OrdersExport;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * Sin acción de crear: las órdenes nacen del checkout, no del backoffice.
     * Sí se puede descargar lo que se está viendo.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('descargarExcel')
                ->label('Descargar Excel')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->tooltip('Descarga lo que ves: la pestaña activa, los filtros y la búsqueda')
                ->action(fn () => $this->descargarExcel()),
        ];
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

            'por_cobrar' => Tab::make('Pendientes de pago')
                ->badge(fn () => $this->countFor(fn (Builder $q) => $q->where('status', OrderStatus::PendingPayment)))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::PendingPayment)),

            'en_proceso' => Tab::make('En proceso')
                ->badge(fn () => $this->countFor(fn (Builder $q) => $q->whereIn('status', $enProceso)))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $enProceso)),

            'entregados' => Tab::make('Entregados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Delivered)),

            'todos' => Tab::make('Todos'),
        ];
    }

    /**
     * Excel con lo que muestra el listado: pestaña activa, filtros (incluido el
     * rango de fechas) y búsqueda. Si no hay nada, avisa en vez de bajar un
     * archivo vacío.
     */
    public function descargarExcel(): ?StreamedResponse
    {
        $ruta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pedidos-'.Str::uuid().'.xlsx';

        try {
            $total = (new OrdersExport)->write($this->getFilteredSortedTableQuery(), $ruta);
        } catch (\Throwable $e) {
            @unlink($ruta);

            throw $e;
        }

        if ($total === 0) {
            @unlink($ruta);

            Notification::make()
                ->warning()
                ->title('No hay pedidos para descargar')
                ->body('Con la pestaña, los filtros y la búsqueda actuales no hay resultados.')
                ->send();

            return null;
        }

        return response()->streamDownload(function () use ($ruta): void {
            readfile($ruta);
            @unlink($ruta);
        }, $this->nombreDelArchivo(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** `pedidos-y-cotizaciones_por-atender_2026-09-01_a_2026-09-30.xlsx` */
    private function nombreDelArchivo(): string
    {
        $pestanas = $this->getTabs();
        $activa = $this->activeTab ?? $this->getDefaultActiveTab();
        $etiqueta = isset($pestanas[$activa]) ? $pestanas[$activa]->getLabel() : 'todos';

        $desde = data_get($this->tableFilters, 'fecha.desde');
        $hasta = data_get($this->tableFilters, 'fecha.hasta');

        $rango = match (true) {
            filled($desde) && filled($hasta) => "{$desde}_a_{$hasta}",
            filled($desde) => "desde_{$desde}",
            filled($hasta) => "hasta_{$hasta}",
            default => 'al_'.now()->toDateString(),
        };

        return 'pedidos-y-cotizaciones_'.Str::slug((string) $etiqueta).'_'.$rango.'.xlsx';
    }

    private function countFor(callable $filter): int
    {
        return $filter(static::getResource()::getEloquentQuery())->count();
    }
}
