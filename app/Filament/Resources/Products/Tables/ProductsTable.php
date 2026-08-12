<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Gestor de inventario (REQ-10). */
class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Las columnas leen marca, categoría e imagen: se precargan para no
            // disparar una consulta por renglón (y porque preventLazyLoading
            // convierte ese descuido en excepción).
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['brand', 'category', 'primaryImage']))
            ->columns([
                ImageColumn::make('primaryImage.path')
                    ->disk('site')
                    ->label('')
                    ->height(36),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->size('xs'),

                TextColumn::make('name')
                    ->label('Producto')
                    ->description(fn (Product $r) => $r->brand?->name)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('price')
                    ->label('Precio')
                    ->money('MXN')
                    ->sortable()
                    ->alignEnd(),

                // Editable en línea: ajustar una existencia no debería costar
                // abrir una ficha completa.
                TextColumn::make('stock_qty')
                    ->label('Existencia')
                    ->sortable()
                    ->alignEnd()
                    ->badge()
                    ->color(fn (Product $r) => match (true) {
                        $r->is_on_demand => 'info',
                        $r->stock_qty <= 0 => 'danger',
                        $r->isLowStock() => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (Product $r) => $r->is_on_demand ? 'Bajo pedido' : $r->stock_qty),

                TextColumn::make('low_stock_threshold')
                    ->label('Umbral')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('max_direct_purchase')
                    ->label('Máx. en línea')
                    ->alignEnd()
                    ->tooltip('Arriba de esta cantidad el pedido pasa a cotización')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Filter::make('stock_bajo')
                    ->label('Existencia baja o agotada')
                    ->query(fn (Builder $query) => $query->where('is_on_demand', false)
                        ->whereColumn('stock_qty', '<=', 'low_stock_threshold')),

                Filter::make('agotado')
                    ->label('Sin existencia')
                    ->query(fn (Builder $query) => $query->where('is_on_demand', false)->where('stock_qty', '<=', 0)),

                SelectFilter::make('category')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->searchable(),

                SelectFilter::make('brand')
                    ->label('Marca')
                    ->relationship('brand', 'name')
                    ->searchable(),

                TernaryFilter::make('is_on_demand')->label('Bajo pedido'),
                TernaryFilter::make('is_rental')->label('Equipo de renta'),
                TernaryFilter::make('is_active')->label('Activo'),
            ])
            ->recordActions([
                self::adjustStockAction(),
                EditAction::make()->label('Editar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkStockAction(),
                    self::bulkThresholdAction(),
                ]),
            ]);
    }

    private static function adjustStockAction(): Action
    {
        return Action::make('existencia')
            ->label('Ajustar')
            ->icon('heroicon-m-adjustments-horizontal')
            ->schema([
                TextInput::make('stock_qty')
                    ->label('Existencia')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->default(fn (Product $record) => $record->stock_qty),

                TextInput::make('low_stock_threshold')
                    ->label('Umbral de alerta')
                    ->numeric()
                    ->minValue(0)
                    ->default(fn (Product $record) => $record->low_stock_threshold),

                Toggle::make('is_on_demand')
                    ->label('Marcar como bajo pedido')
                    ->helperText('Siempre pasa a cotización, sin importar la cantidad.')
                    ->default(fn (Product $record) => $record->is_on_demand),
            ])
            ->action(function (Product $record, array $data) {
                $record->update($data);

                Notification::make()->success()->title("{$record->sku} actualizado")->send();
            });
    }

    /** REQ-10: ajuste masivo, para recibos de mercancía y conteos físicos. */
    private static function bulkStockAction(): BulkAction
    {
        return BulkAction::make('existenciaMasiva')
            ->label('Ajustar existencia')
            ->icon('heroicon-m-squares-plus')
            ->schema([
                Radio::make('modo')
                    ->label('Operación')
                    ->options([
                        'sumar' => 'Sumar a la existencia actual (entrada de mercancía)',
                        'restar' => 'Restar de la existencia actual (salida o merma)',
                        'fijar' => 'Fijar el valor exacto (conteo físico)',
                    ])
                    ->default('sumar')
                    ->required(),

                TextInput::make('cantidad')
                    ->label('Cantidad')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                $cantidad = (int) $data['cantidad'];

                foreach ($records as $product) {
                    $product->update([
                        'stock_qty' => match ($data['modo']) {
                            'sumar' => $product->stock_qty + $cantidad,
                            'restar' => max(0, $product->stock_qty - $cantidad),
                            default => $cantidad,
                        },
                    ]);
                }

                Notification::make()
                    ->success()
                    ->title('Existencia ajustada')
                    ->body("{$records->count()} productos actualizados.")
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function bulkThresholdAction(): BulkAction
    {
        return BulkAction::make('umbralMasivo')
            ->label('Cambiar umbral de alerta')
            ->icon('heroicon-m-bell-alert')
            ->schema([
                TextInput::make('low_stock_threshold')
                    ->label('Nuevo umbral')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                Product::whereKey($records->pluck('id'))
                    ->update(['low_stock_threshold' => (int) $data['low_stock_threshold']]);

                Notification::make()->success()->title('Umbral actualizado')->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
