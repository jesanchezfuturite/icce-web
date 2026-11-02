<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\RentalCoverage;
use App\Models\Category;
use App\Models\ProductImage;
use App\Rules\OptimizableImage;
use App\Support\ProductImageOptimizer;
use App\Support\ProductImageStorage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                    ->label('Precio de comparación (opcional)')
                    ->numeric()
                    ->minValue(0)
                    ->gt('price')
                    ->default(null)
                    ->prefix('$')
                    ->helperText('El precio «de antes». Si es mayor al precio, el sitio lo muestra tachado junto al precio actual, con el porcentaje de ahorro. Déjalo vacío si no hay oferta.'),
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
                self::pdfUpload('tech_sheet_pdf', 'Ficha técnica (PDF)', 'ficha-tecnica'),
                self::pdfUpload('safety_sheet_pdf', 'Hoja de seguridad (PDF)', 'hoja-de-seguridad'),

                // Pares característica → valor, que es como la ficha los dibuja. El dato
                // interno `imagen_origen` (de la carga inicial) no se muestra ni se pierde.
                KeyValue::make('specs')
                    ->label('Especificaciones')
                    ->keyLabel('Característica')
                    ->valueLabel('Valor')
                    ->addActionLabel('Agregar característica')
                    ->reorderable()
                    ->default(null)
                    ->formatStateUsing(fn ($state) => collect((array) $state)->except('imagen_origen')->all())
                    ->dehydrateStateUsing(fn ($state, $record) => ($merged = array_filter(
                        array_merge(
                            collect((array) $record?->specs)->only('imagen_origen')->all(),
                            collect((array) $state)->filter(fn ($value, $key) => filled($key) && filled($value))->all(),
                        ),
                        fn ($value) => filled($value),
                    )) === [] ? null : $merged)
                    ->helperText('Ejemplo: «Color» → «Gris», «Presentación» → «Cubeta 19 L». Se muestran como tabla en la ficha del producto.')
                    ->columnSpanFull(),

                Repeater::make('images')
                    ->relationship()
                    ->label('Imágenes del producto')
                    ->schema([
                        FileUpload::make('path')
                            ->label('Imagen')
                            ->disk(ProductImage::DISK)
                            ->directory(ProductImage::UPLOAD_DIRECTORY)
                            ->visibility('public')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            // Con GD se acepta hasta 10 MB y se reduce al guardar; sin GD,
                            // el archivo debe pesar 400 KB o menos.
                            ->maxSize(fn () => ProductImageOptimizer::isAvailable() ? 10240 : ProductImageOptimizer::MAX_BYTES / 1024)
                            ->rules([new OptimizableImage])
                            ->required()
                            ->deleteUploadedFileUsing(fn () => null)
                            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file, $livewire) => ProductImageStorage::store($file, data_get($livewire, 'data.name')))
                            ->helperText(fn () => ProductImageOptimizer::isAvailable()
                                ? 'Se recomienda subir archivos WebP. El peso máximo es de 400 KB: si tu imagen pesa más, se reduce sola al guardar (se convierte a WebP y se limita a 1600 px).'
                                : 'Se recomienda subir archivos WebP. El peso máximo es de 400 KB.')
                            ->columnSpanFull(),
                        TextInput::make('alt')
                            ->label('Texto alternativo')
                            ->maxLength(150)
                            ->placeholder('Describe la imagen: «Cubeta de sellador Pourthane NS»')
                            ->helperText('Lo leen los lectores de pantalla y lo usa Google. Si lo dejas vacío se usa el nombre del producto.'),
                        Toggle::make('is_primary')
                            ->label('Imagen principal')
                            ->helperText('La que se ve en el catálogo y en las tarjetas. Si no marcas ninguna, la primera lo es.'),
                    ])
                    ->orderColumn('sort_order')
                    ->reorderable()
                    ->addActionLabel('Agregar imagen')
                    ->itemLabel(fn (array $state) => $state['alt'] ?: 'Imagen')
                    ->defaultItems(0)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->required(),
                Toggle::make('is_featured')
                    ->label('Destacado')
                    ->required(),
                TextInput::make('meta_title')
                    ->label('Título para buscadores')
                    ->default(null)
                    ->helperText('Máximo 191 caracteres. Para Google conviene que no pase de unos 60.'),
                Textarea::make('meta_description')
                    ->label('Descripción para buscadores')
                    ->rows(3)
                    ->default(null)
                    ->helperText('Máximo 255 caracteres. Google muestra unos 155, así que lo ideal es una o dos frases cortas.')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Sube un PDF a public/fichas. Nunca borra archivos del disco: una misma
     * ficha puede servir a varios productos (la carga inicial las comparte).
     */
    private static function pdfUpload(string $field, string $label, string $suffix): FileUpload
    {
        return FileUpload::make($field)
            ->label($label)
            ->disk(ProductImage::DISK)
            ->directory('fichas')
            ->visibility('public')
            ->acceptedFileTypes(['application/pdf'])
            ->maxSize(10240)
            ->downloadable()
            ->openable()
            ->deleteUploadedFileUsing(fn () => null)
            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file, Get $get) => (
                Str::slug((string) ($get('name') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)))
                ?: 'producto'
            ).'-'.$suffix.'-'.Str::lower(Str::random(5)).'.pdf')
            ->helperText('Sube el archivo PDF (máximo 10 MB). Aparece en la ficha del producto y en la página de Descargas.')
            ->default(null);
    }
}
