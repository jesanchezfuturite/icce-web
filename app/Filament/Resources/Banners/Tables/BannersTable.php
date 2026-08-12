<?php

namespace App\Filament\Resources\Banners\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BannersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->disk('site')
                    ->label('Imagen')
                    ->height(44),

                TextColumn::make('title')
                    ->label('Título')
                    ->description(fn ($record) => $record->eyebrow)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('cta_label')
                    ->label('Botón')
                    ->description(fn ($record) => $record->cta_url)
                    ->placeholder('Sin botón'),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                TextColumn::make('starts_at')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->placeholder('Siempre')
                    ->toggleable(),

                TextColumn::make('ends_at')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->placeholder('Sin caducidad')
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
