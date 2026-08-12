<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contenido')
                    ->schema([
                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(200)
                            ->live(onBlur: true)
                            // El slug se propone desde el título pero sigue siendo
                            // editable: cambiarlo en un artículo publicado rompe su URL.
                            ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug((string) $state))),

                        TextInput::make('slug')
                            ->label('URL (slug)')
                            ->required()
                            ->maxLength(200)
                            ->unique(ignoreRecord: true)
                            ->helperText('Se publica en /blog/{slug}. Cambiarlo invalida los enlaces existentes.'),

                        TextInput::make('topic')
                            ->label('Tema')
                            ->maxLength(60)
                            ->datalist(['Diseño de pisos', 'Pisos superplanos', 'Juntas', 'Reparación', 'Curado']),

                        Textarea::make('excerpt')
                            ->label('Entradilla')
                            ->rows(3)
                            ->maxLength(400)
                            ->columnSpanFull(),

                        Textarea::make('body')
                            ->label('Cuerpo')
                            ->rows(18)
                            ->helperText('Separa párrafos con una línea en blanco. Usa "## " al inicio de la línea para subtítulos.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Publicación')
                    ->schema([
                        FileUpload::make('cover_image')
                            ->label('Imagen de portada')
                            ->image()
                            ->imageEditor()
                            ->disk('site')
                            ->directory('images/blog'),

                        Select::make('author_id')
                            ->label('Autor')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->preload(),

                        DateTimePicker::make('published_at')
                            ->label('Fecha de publicación')
                            ->helperText('Vacío o a futuro = borrador, no visible en el sitio.')
                            ->seconds(false),

                        TextInput::make('reading_minutes')
                            ->label('Minutos de lectura')
                            ->numeric()
                            ->minValue(1)
                            ->default(5)
                            ->required(),

                        Toggle::make('is_featured')
                            ->label('Destacado en el blog'),
                    ])
                    ->columns(2),

                Section::make('SEO')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta title')
                            ->maxLength(191),

                        Textarea::make('meta_description')
                            ->label('Meta description')
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
}
