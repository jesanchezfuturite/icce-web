<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Alerta de existencias en el escritorio (REQ-10). */
class StockBajoTable extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Existencia por reponer')
            ->description('Productos de venta al o por debajo de su umbral de alerta.')
            ->query(fn (): Builder => Product::query()
                ->active()
                ->forSale()
                ->lowStock()
                ->with(['brand', 'category'])
                ->orderBy('stock_qty'))
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable()->size('xs'),

                TextColumn::make('name')
                    ->label('Producto')
                    ->description(fn (Product $r) => $r->brand?->name)
                    ->searchable()
                    ->limit(50)
                    ->wrap(),

                TextColumn::make('category.name')->label('Categoría')->badge(),

                TextColumn::make('stock_qty')
                    ->label('Existencia')
                    ->badge()
                    ->alignEnd()
                    ->color(fn (Product $r) => $r->stock_qty <= 0 ? 'danger' : 'warning'),

                TextColumn::make('low_stock_threshold')->label('Umbral')->alignEnd(),
            ])
            ->recordUrl(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
            ->paginationPageOptions([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Sin alertas de existencia')
            ->emptyStateDescription('Ningún producto de venta está por debajo de su umbral.');
    }
}
