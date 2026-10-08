<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\RentalCoverage;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('brand_id')
                    ->label('Marca')
                    ->relationship('brand', 'name')
                    ->default(null),
                Select::make('category_id')
                    ->label('Categoría')
                    ->options(fn () => collect(Category::pathsById())
                        ->map(fn (array $names) => implode(' › ', $names))
                        ->sort()
                        ->all())
                    ->searchable()
                    ->placeholder('Sin categoría')
                    ->helperText('Cambiarla aquí mueve el producto a otra categoría. Sin categoría, el producto no aparece dentro de ninguna.')
                    ->default(null),
                TextInput::make('sku')
                    ->label('SKU')
                    ->required(),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                TextInput::make('slug')
                    ->label('Slug (parte de la URL)')
                    ->required(),
                TextInput::make('short_description')
                    ->label('Descripción corta')
                    ->default(null),
                Textarea::make('description')
                    ->label('Descripción')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('Precio (antes de IVA)')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
                TextInput::make('compare_at_price')
                    ->label('Precio de comparación')
                    ->numeric()
                    ->default(null)
                    ->prefix('$'),
                TextInput::make('unit')
                    ->label('Unidad')
                    ->required()
                    ->default('pieza'),
                TextInput::make('stock_qty')
                    ->label('Existencia')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('low_stock_threshold')
                    ->label('Umbral de existencia baja')
                    ->required()
                    ->numeric()
                    ->default(5),
                TextInput::make('max_direct_purchase')
                    ->label('Máximo de compra directa (piezas)')
                    ->required()
                    ->numeric()
                    ->default(10),
                Toggle::make('is_on_demand')
                    ->label('Bajo pedido')
                    ->required(),
                Toggle::make('is_for_sale')
                    ->label('Disponible para venta')
                    ->required(),
                Toggle::make('is_rental')
                    ->label('En renta')
                    ->helperText('Un equipo en renta se muestra en «Renta de equipos» y no en el catálogo de venta.')
                    ->live()
                    ->required(),
                Select::make('rental_coverage')
                    ->label('Cobertura de la renta')
                    ->options(RentalCoverage::class)
                    ->visible(fn ($get) => (bool) $get('is_rental'))
                    ->required(fn ($get) => (bool) $get('is_rental'))
                    ->default(null),
                Toggle::make('rental_includes_operator')
                    ->label('Incluye operador (conductor)')
                    ->visible(fn ($get) => (bool) $get('is_rental'))
                    ->default(false),
                TextInput::make('tech_sheet_pdf')
                    ->label('Ficha técnica (archivo PDF)')
                    ->default(null),
                TextInput::make('safety_sheet_pdf')
                    ->label('Hoja de seguridad (archivo PDF)')
                    ->default(null),
                Textarea::make('specs')
                    ->label('Especificaciones')
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->required(),
                Toggle::make('is_featured')
                    ->label('Destacado')
                    ->required(),
                TextInput::make('meta_title')
                    ->label('Título para buscadores')
                    ->default(null),
                TextInput::make('meta_description')
                    ->label('Descripción para buscadores')
                    ->default(null),
            ]);
    }
}
