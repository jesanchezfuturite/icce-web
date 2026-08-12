<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('client')
                    ->default(null),
                TextInput::make('location')
                    ->default(null),
                TextInput::make('year')
                    ->numeric()
                    ->default(null),
                TextInput::make('area_m2')
                    ->numeric()
                    ->default(null),
                TextInput::make('summary')
                    ->default(null),
                Textarea::make('body')
                    ->default(null)
                    ->columnSpanFull(),
                FileUpload::make('cover_image')
                    ->disk('site')
                    ->directory('images/proyectos')
                    ->image(),
                Textarea::make('services')
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('is_featured')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
