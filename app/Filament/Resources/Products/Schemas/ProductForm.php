<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\RentalCoverage;
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
                    ->relationship('brand', 'name')
                    ->default(null),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),
                TextInput::make('sku')
                    ->label('SKU')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('short_description')
                    ->default(null),
                Textarea::make('description')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('$'),
                TextInput::make('compare_at_price')
                    ->numeric()
                    ->default(null)
                    ->prefix('$'),
                TextInput::make('unit')
                    ->required()
                    ->default('pieza'),
                TextInput::make('stock_qty')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('low_stock_threshold')
                    ->required()
                    ->numeric()
                    ->default(5),
                TextInput::make('max_direct_purchase')
                    ->required()
                    ->numeric()
                    ->default(10),
                Toggle::make('is_on_demand')
                    ->required(),
                Toggle::make('is_rental')
                    ->required(),
                Toggle::make('is_for_sale')
                    ->required(),
                Select::make('rental_coverage')
                    ->options(RentalCoverage::class)
                    ->default(null),
                TextInput::make('tech_sheet_pdf')
                    ->default(null),
                TextInput::make('safety_sheet_pdf')
                    ->default(null),
                Textarea::make('specs')
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('is_featured')
                    ->required(),
                TextInput::make('meta_title')
                    ->default(null),
                TextInput::make('meta_description')
                    ->default(null),
            ]);
    }
}
