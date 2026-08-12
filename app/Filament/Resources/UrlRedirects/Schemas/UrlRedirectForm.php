<?php

namespace App\Filament\Resources\UrlRedirects\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UrlRedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('old_path')
                    ->required(),
                TextInput::make('new_path')
                    ->default(null),
                TextInput::make('status_code')
                    ->required()
                    ->numeric()
                    ->default(301),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('hits')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('last_hit_at'),
            ]);
    }
}
