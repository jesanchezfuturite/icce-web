<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        // Las rutas se calculan una vez por render, no por fila.
        $paths = null;
        $pathOf = function (Category $record) use (&$paths): array {
            $paths ??= Category::pathsById();

            return $paths[$record->id] ?? [$record->name];
        };

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Categoría')
                    ->description(fn (Category $record) => $record->slug)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('level')
                    ->label('Nivel')
                    ->state(fn (Category $record) => count($pathOf($record)))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('parent_path')
                    ->label('Pertenece a')
                    ->state(fn (Category $record) => implode(' › ', array_slice($pathOf($record), 0, -1)))
                    ->placeholder('Principal')
                    ->wrap(),

                TextColumn::make('products_count')
                    ->label('Productos directos')
                    ->counts('products')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            // Arrastrar para acomodar. Conviene filtrar primero por «Categoría padre»
            // para reordenar sólo a las hermanas.
            ->reorderable('sort_order')
            // El reordenamiento escribe en bloque, sin eventos del modelo.
            ->afterReordering(fn () => Cache::forget(Category::NAV_CACHE_KEY))
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Categoría padre')
                    ->options(fn () => Category::query()
                        ->whereIn('id', Category::query()->whereNotNull('parent_id')->pluck('parent_id'))
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
