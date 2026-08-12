<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                            ->formatStateUsing(fn ($state) => $state instanceof OrderType ? $state->label() : $state)
                            ->disabled(),
                        TextInput::make('status')
                            ->label('Estatus')
                            ->formatStateUsing(fn ($state) => $state?->label())
                            ->disabled()
                            ->helperText('Se cambia con la acción «Cambiar estatus» del listado.'),
                        Select::make('assigned_to')
                            ->label('Agente que atiende')
                            ->options(fn () => User::whereIn('role', [UserRole::Sales, UserRole::Admin])
                                ->orderBy('name')->pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->columns(4),

                Section::make('Cliente')
                    ->schema([
                        TextInput::make('customer_name')->label('Nombre')->disabled(),
                        TextInput::make('customer_company')->label('Empresa')->disabled(),
                        TextInput::make('customer_email')->label('Correo')->disabled(),
                        TextInput::make('customer_phone')->label('Teléfono')->disabled(),
                    ])
                    ->columns(4)
                    ->collapsible(),

                Section::make('Partidas')
                    ->description('Ajusta el precio unitario para la propuesta. El precio de lista se conserva.')
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
                                    ->placeholder('Sin ajuste'),
                            ])
                            ->columns(6)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->itemLabel(fn (array $state) => $state['product_name'] ?? null),
                    ]),

                Section::make('Logística y totales')
                    ->schema([
                        DatePicker::make('estimated_delivery_date')->label('Entrega estimada')->displayFormat('d/m/Y'),
                        TextInput::make('tracking_number')->label('Guía'),
                        TextInput::make('carrier')->label('Transporte'),
                        DatePicker::make('quote_valid_until')->label('Vigencia de cotización')->displayFormat('d/m/Y'),

                        TextInput::make('subtotal')->label('Subtotal')->prefix('$')->disabled(),
                        TextInput::make('discount_amount')->label('Descuento')->prefix('$')->numeric()->minValue(0),
                        TextInput::make('tax_amount')->label('IVA')->prefix('$')->disabled(),
                        TextInput::make('total_amount')->label('Total')->prefix('$')->disabled(),
                    ])
                    ->columns(4),

                Section::make('Notas')
                    ->schema([
                        Textarea::make('customer_notes')->label('Notas del cliente')->rows(3)->disabled(),
                        Textarea::make('internal_notes')->label('Notas internas')->rows(3)
                            ->helperText('No se comparten con el cliente.'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}
