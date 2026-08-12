<?php

namespace App\Filament\Resources\Projects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')
                    ->disk('site')
                    ->label('Portada')
                    ->height(40),

                TextColumn::make('title')
                    ->label('Proyecto')
                    ->description(fn ($record) => $record->client)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('location')
                    ->label('Ubicación')
                    ->searchable(),

                TextColumn::make('year')
                    ->label('Año')
                    ->sortable(),

                TextColumn::make('area_m2')
                    ->label('Superficie')
                    ->numeric()
                    ->suffix(' m²')
                    ->sortable(),

                IconColumn::make('is_featured')
                    ->label('Destacado')
                    ->boolean(),

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
