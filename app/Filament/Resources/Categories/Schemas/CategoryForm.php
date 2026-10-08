<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label('Categoría padre')
                    ->options(fn (?Category $record) => Category::parentOptionsFor($record))
                    ->placeholder('Ninguna (categoría principal)')
                    ->helperText('Hasta 4 niveles. Para mover esta categoría a otro lugar del árbol, elige aquí su nuevo padre.')
                    ->searchable()
                    ->default(null),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                TextInput::make('slug')
                    ->label('Slug (parte de la URL)')
                    ->required(),
                Textarea::make('description')
                    ->label('Descripción')
                    ->default(null)
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->label('Imagen')
                    ->disk('site')
                    ->directory('images/categorias')
                    ->image(),
                Toggle::make('is_active')
                    ->label('Activa')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Orden')
                    ->helperText('Posición entre las categorías que comparten el mismo padre; el menor va primero.')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('meta_title')
                    ->label('Título para buscadores')
                    ->default(null),
                TextInput::make('meta_description')
                    ->label('Descripción para buscadores')
                    ->default(null),
            ]);
    }
}
