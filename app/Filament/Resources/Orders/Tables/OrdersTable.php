<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Actions\Orders\ChangeOrderStatus;
use App\Actions\Orders\SendQuote;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('agent'))
            ->columns([
                TextColumn::make('folio')
                    ->label('Folio')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Order $r) => $r->created_at->translatedFormat('d M Y')),

                // Sin type-hint del enum: Filament resuelve por tipo desde el
                // contenedor y un enum no es instanciable.
                TextColumn::make('order_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => $state === OrderType::Quote ? 'info' : 'primary'),

                TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->description(fn (Order $r) => $r->customer_company)
                    ->searchable(['customer_name', 'customer_company', 'customer_email'])
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => $state?->color()),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('MXN')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('estimated_delivery_date')
                    ->label('Entrega')
                    ->date('d/m/Y')
                    ->placeholder('Sin fecha')
                    ->sortable()
                    // Una entrega comprometida que ya se pasó necesita saltar a la vista
                    ->color(fn (Order $r) => $r->estimated_delivery_date
                        && $r->estimated_delivery_date->isPast()
                        && ! $r->status->isFinal() ? 'danger' : null),

                TextColumn::make('agent.name')
                    ->label('Atiende')
                    ->placeholder('Sin asignar')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()),

                SelectFilter::make('order_type')
                    ->label('Tipo')
                    ->options(collect(OrderType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()),

                // Opciones explícitas: el filtro apunta a la columna
                // `assigned_to`, que no se llama igual que la relación `agent`.
                SelectFilter::make('assigned_to')
                    ->label('Agente')
                    ->options(fn () => User::whereIn('role', [UserRole::Sales, UserRole::Admin])
                        ->orderBy('name')->pluck('name', 'id')),

                // El parámetro debe llamarse $query: Filament inyecta por nombre
                // y, si no lo reconoce, intenta resolver el tipo del contenedor.
                Filter::make('atrasados')
                    ->label('Entrega vencida')
                    ->query(fn (Builder $query) => $query->whereNotNull('estimated_delivery_date')
                        ->whereDate('estimated_delivery_date', '<', now())
                        ->whereNotIn('status', [OrderStatus::Delivered->value, OrderStatus::Cancelled->value])),
            ])
            ->recordActions([
                self::changeStatusAction(),
                self::sendQuoteAction(),
                ActionGroup::make([
                    EditAction::make()->label('Ver detalle'),
                    self::downloadQuoteAction(),
                    self::assignAgentAction(),
                ])->label('Más'),
            ]);
    }

    /** REQ-05: mover el estatus y comprometer la fecha de entrega. */
    private static function changeStatusAction(): Action
    {
        return Action::make('estatus')
            ->label('Cambiar estatus')
            ->icon('heroicon-m-arrow-path')
            ->color('primary')
            ->visible(fn (Order $record) => ! $record->status->isFinal())
            ->schema([
                Select::make('status')
                    ->label('Nuevo estatus')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all())
                    ->default(fn (Order $record) => $record->status->value)
                    ->required()
                    ->live(),

                DatePicker::make('estimated_delivery_date')
                    ->label('Fecha estimada de entrega')
                    ->default(fn (Order $record) => $record->estimated_delivery_date)
                    ->helperText('Es la fecha que verá el cliente en su portal.')
                    ->displayFormat('d/m/Y'),

                TextInput::make('tracking_number')
                    ->label('Guía de rastreo')
                    ->default(fn (Order $record) => $record->tracking_number)
                    ->visible(fn ($get) => in_array($get('status'), [OrderStatus::Shipped->value, OrderStatus::Delivered->value], true)),

                TextInput::make('carrier')
                    ->label('Paquetería o transporte')
                    ->default(fn (Order $record) => $record->carrier)
                    ->visible(fn ($get) => in_array($get('status'), [OrderStatus::Shipped->value, OrderStatus::Delivered->value], true)),

                Textarea::make('note')
                    ->label('Nota para la bitácora')
                    ->rows(2)
                    ->helperText('Se incluye en el correo si avisas al cliente.'),

                Checkbox::make('notify')
                    ->label('Avisar al cliente por correo')
                    ->default(true),
            ])
            ->action(function (Order $record, array $data, ChangeOrderStatus $changeStatus) {
                $changeStatus(
                    order: $record,
                    to: OrderStatus::from($data['status']),
                    author: auth()->user(),
                    note: $data['note'] ?? null,
                    notifyCustomer: (bool) ($data['notify'] ?? false),
                    estimatedDeliveryDate: $data['estimated_delivery_date'] ?? null,
                    trackingNumber: $data['tracking_number'] ?? null,
                    carrier: $data['carrier'] ?? null,
                );

                Notification::make()
                    ->success()
                    ->title("{$record->folio} → {$record->fresh()->status->label()}")
                    ->body(($data['notify'] ?? false) ? 'Se avisó al cliente por correo.' : 'Sin aviso al cliente.')
                    ->send();
            });
    }

    /** REQ-09: enviar la propuesta ajustada con su PDF. */
    private static function sendQuoteAction(): Action
    {
        return Action::make('enviarCotizacion')
            ->label('Enviar cotización')
            ->icon('heroicon-m-paper-airplane')
            ->color('info')
            ->visible(fn (Order $record) => $record->order_type === OrderType::Quote && ! $record->status->isFinal())
            ->schema([
                TextInput::make('discount_amount')
                    ->label('Descuento sobre el subtotal')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$')
                    ->default(fn (Order $record) => (float) $record->discount_amount)
                    ->helperText('Los ajustes por partida se editan en el detalle de la orden.'),

                DatePicker::make('valid_until')
                    ->label('Vigencia de la propuesta')
                    ->default(fn (Order $record) => $record->quote_valid_until ?? now()->addDays(15))
                    ->displayFormat('d/m/Y'),

                Textarea::make('message')
                    ->label('Mensaje para el cliente')
                    ->rows(3)
                    ->placeholder('Aplicamos precio de proyecto por volumen. Entrega en 8 días hábiles.'),
            ])
            ->modalDescription('Se recalculan los totales con los precios ajustados y se envía el PDF al cliente.')
            ->action(function (Order $record, array $data, SendQuote $sendQuote) {
                $sendQuote(
                    order: $record,
                    author: auth()->user(),
                    message: $data['message'] ?? null,
                    validUntil: $data['valid_until'] ?? null,
                    discountAmount: (float) ($data['discount_amount'] ?? 0),
                );

                Notification::make()
                    ->success()
                    ->title("Cotización {$record->folio} enviada")
                    ->body('El cliente recibió la propuesta en PDF.')
                    ->send();
            });
    }

    private static function downloadQuoteAction(): Action
    {
        return Action::make('pdf')
            ->label('Descargar PDF')
            ->icon('heroicon-m-arrow-down-tray')
            ->visible(fn (Order $record) => $record->order_type === OrderType::Quote)
            ->action(fn (Order $record, SendQuote $sendQuote): StreamedResponse => response()->streamDownload(
                fn () => print ($sendQuote->pdf($record)->output()),
                "Cotizacion-{$record->folio}.pdf",
            ));
    }

    private static function assignAgentAction(): Action
    {
        return Action::make('asignar')
            ->label('Asignar agente')
            ->icon('heroicon-m-user-plus')
            ->schema([
                Select::make('assigned_to')
                    ->label('Agente')
                    ->options(fn () => User::whereIn('role', [UserRole::Sales, UserRole::Admin])
                        ->orderBy('name')->pluck('name', 'id'))
                    ->default(fn (Order $record) => $record->assigned_to)
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Order $record, array $data) {
                $record->update(['assigned_to' => $data['assigned_to']]);

                Notification::make()->success()->title('Agente asignado')->send();
            });
    }
}
