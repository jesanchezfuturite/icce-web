<?php

namespace App\Filament\Resources\Brands\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')
                    ->disk('site')
                    ->label('Logo')
                    ->height(32),

                TextColumn::make('name')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('products_count')
                    ->label('Productos')
                    ->counts('products')
                    ->sortable(),

                IconColumn::make('is_featured')
                    ->label('En el carrusel')
                    ->boolean(),

                TextColumn::make('website')
                    ->label('Sitio oficial')
                    ->url(fn ($record) => $record->website)
                    ->openUrlInNewTab()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
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
