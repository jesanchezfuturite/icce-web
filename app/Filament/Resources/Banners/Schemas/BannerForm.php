<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('eyebrow')
                    ->default(null),
                TextInput::make('title')
                    ->required(),
                TextInput::make('subtitle')
                    ->default(null),
                FileUpload::make('image_path')
                    ->disk('site')
                    ->directory('images/banners')
                    ->image()
                    ->required(),
                TextInput::make('cta_label')
                    ->default(null),
                TextInput::make('cta_url')
                    ->url()
                    ->default(null),
                TextInput::make('secondary_cta_label')
                    ->default(null),
                TextInput::make('secondary_cta_url')
                    ->url()
                    ->default(null),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('starts_at'),
                DateTimePicker::make('ends_at'),
            ]);
    }
}
