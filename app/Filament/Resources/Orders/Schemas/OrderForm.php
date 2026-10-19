<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Support\QuoteTotals;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Detalle de una orden en el backoffice.
 *
 * Los datos del cliente y el snapshot de las partidas son de sólo lectura: una
 * orden histórica no debe reescribirse. Lo único editable en las partidas es
 * `quoted_unit_price`, que es precisamente el ajuste que hace el agente
 * (REQ-09) sin perder el precio de lista original.
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Orden')
                    ->schema([
                        TextInput::make('folio')->label('Folio')->disabled(),
                        TextInput::make('order_type')
                            ->label('Tipo')
                            ->formatStateUsing(fn ($state) => match (true) {
                                $state instanceof OrderType => $state->label(),
                                is_string($state) => OrderType::from($state)->label(),
                                default => $state,
                            })
                            ->disabled(),
                        TextInput::make('status')
                            ->label('Estatus')
                            ->formatStateUsing(fn ($state) => match (true) {
                                $state instanceof OrderStatus => $state->label(),
                                is_string($state) => OrderStatus::from($state)->label(),
                                default => $state,
                            })
                            ->disabled()
                            ->helperText('Se cambia con la acción «Cambiar estatus» del listado.'),
                        Select::make('assigned_to')
                            ->label('Agente que atiende')
                            ->options(fn () => User::whereIn('role', [UserRole::Sales, UserRole::Admin])
                                ->orderBy('name')->pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make('Cliente')
                    ->schema([
                        TextInput::make('customer_name')->label('Nombre')->disabled(),
                        TextInput::make('customer_company')->label('Empresa')->disabled(),
                        TextInput::make('customer_email')->label('Correo')->disabled(),
                        TextInput::make('customer_phone')->label('Teléfono')->disabled(),
                    ])
                    ->columns(4)
                    ->collapsible()
                    ->columnSpanFull(),

                Section::make('Partidas')
                    ->description('Ajusta el precio unitario para la propuesta; el precio de lista se conserva. El importe y el total se actualizan al teclear, y se guardan con «Guardar cambios».')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->label('')
                            ->schema([
                                TextInput::make('product_name')->label('Producto')->disabled()->columnSpan(2),
                                TextInput::make('product_sku')->label('SKU')->disabled(),
                                TextInput::make('quantity')->label('Cant.')->numeric()->disabled(),
                                TextInput::make('unit_price')->label('Lista')->prefix('$')->disabled(),
                                TextInput::make('quoted_unit_price')
                                    ->label('Precio cotizado')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('$')
                                    ->placeholder('Sin ajuste')
                                    ->live(onBlur: true)
                                    ->disabled(fn ($livewire) => ! self::editable($livewire)),
                                TextEntry::make('importe')
                                    ->label('Importe')
                                    ->weight('bold')
                                    ->state(fn (Get $get) => self::money(QuoteTotals::lineTotal(
                                        $get('quantity'),
                                        QuoteTotals::effectivePrice($get('quoted_unit_price'), $get('unit_price')),
                                    ))),
                            ])
                            ->columns(7)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->itemLabel(fn (array $state) => $state['product_name'] ?? null),
                    ])
                    ->columnSpanFull(),

                Section::make('Logística y totales')
                    ->description(fn ($livewire) => self::editable($livewire)
                        ? null
                        : 'Los precios y el descuento sólo se ajustan mientras la cotización no esté autorizada ni pagada.')
                    ->schema([
                        DatePicker::make('estimated_delivery_date')->label('Entrega estimada')->displayFormat('d/m/Y'),
                        TextInput::make('tracking_number')->label('Guía'),
                        TextInput::make('carrier')->label('Transporte'),
                        DatePicker::make('quote_valid_until')->label('Vigencia de cotización')->displayFormat('d/m/Y'),

                        TextEntry::make('subtotal_vista')
                            ->label('Subtotal')
                            ->state(fn (Get $get, $livewire) => self::money(self::totals($get, $livewire)->subtotal)),
                        TextInput::make('discount_amount')->label('Descuento')->prefix('$')->numeric()->minValue(0)
                            ->default(0)
                            ->live(onBlur: true)
                            ->disabled(fn ($livewire) => ! self::editable($livewire))
                            ->dehydrateStateUsing(fn ($state) => $state ?? 0),
                        TextEntry::make('iva_vista')
                            ->label('IVA')
                            ->state(fn (Get $get, $livewire) => self::money(self::totals($get, $livewire)->tax)),
                        TextEntry::make('total_vista')
                            ->label('Total con descuento')
                            ->weight('bold')
                            ->size('lg')
                            ->state(fn (Get $get, $livewire) => self::money(self::totals($get, $livewire)->total)),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),

                Section::make('Notas')
                    ->schema([
                        Textarea::make('customer_notes')->label('Notas del cliente')->rows(3)->disabled(),
                        Textarea::make('internal_notes')->label('Notas internas')->rows(3)
                            ->helperText('No se comparten con el cliente.'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    private static function order($livewire): ?Order
    {
        $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;

        return $record instanceof Order ? $record : null;
    }

    private static function editable($livewire): bool
    {
        return self::order($livewire)?->pricesAreEditable() ?? false;
    }

    /**
     * Totales para mostrar. Si los precios se pueden ajustar se calculan en vivo
     * con lo que hay en el formulario; si no, se muestra lo ya guardado.
     */
    private static function totals(Get $get, $livewire): QuoteTotals
    {
        if (self::editable($livewire)) {
            return QuoteTotals::calculate(
                collect($get('items') ?? [])->map(fn (array $item) => [
                    $item['quantity'] ?? 0,
                    QuoteTotals::effectivePrice($item['quoted_unit_price'] ?? null, $item['unit_price'] ?? null),
                ]),
                $get('discount_amount'),
            );
        }

        $order = self::order($livewire);
        $subtotal = (float) $order?->subtotal;
        $discount = (float) $order?->discount_amount;

        return new QuoteTotals($subtotal, $discount, round($subtotal - $discount, 2), (float) $order?->tax_amount, (float) $order?->total_amount);
    }

    public static function money(float $amount): string
    {
        return '$'.number_format($amount, 2).' MXN';
    }
}
