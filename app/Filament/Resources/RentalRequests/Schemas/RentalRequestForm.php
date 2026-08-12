<?php

namespace App\Filament\Resources\RentalRequests\Schemas;

use App\Enums\RentalCoverage;
use App\Enums\RentalRequestStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RentalRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('folio')
                    ->required(),
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->default(null),
                TextInput::make('equipment_name')
                    ->required(),
                TextInput::make('client_name')
                    ->required(),
                TextInput::make('company')
                    ->default(null),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('location')
                    ->required(),
                Select::make('coverage')
                    ->options(RentalCoverage::class)
                    ->default('local')
                    ->required(),
                DatePicker::make('start_date'),
                TextInput::make('rental_days')
                    ->numeric()
                    ->default(null),
                Textarea::make('project_description')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->default(null)
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(RentalRequestStatus::class)
                    ->default('new')
                    ->required(),
                TextInput::make('assigned_to')
                    ->numeric()
                    ->default(null),
                DateTimePicker::make('contacted_at'),
                Textarea::make('internal_notes')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
