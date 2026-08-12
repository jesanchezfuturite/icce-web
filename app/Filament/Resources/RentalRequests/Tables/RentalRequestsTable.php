<?php

namespace App\Filament\Resources\RentalRequests\Tables;

use App\Enums\RentalCoverage;
use App\Enums\RentalRequestStatus;
use App\Enums\UserRole;
use App\Models\RentalRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Leads de renta (flujo 3 del AppFlow). */
class RentalRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('agent'))
            ->columns([
                TextColumn::make('folio')
                    ->label('Folio')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (RentalRequest $r) => $r->created_at->diffForHumans()),

                TextColumn::make('equipment_name')
                    ->label('Equipo')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('client_name')
                    ->label('Contacto')
                    ->description(fn (RentalRequest $r) => $r->company)
                    ->searchable(['client_name', 'company', 'email']),

                TextColumn::make('location')
                    ->label('Obra')
                    ->description(fn (RentalRequest $r) => $r->coverage?->label())
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->placeholder('Sin fecha')
                    ->sortable(),

                TextColumn::make('rental_days')
                    ->label('Días')
                    ->alignEnd(),

                // Sin type-hint del enum: Filament resuelve por tipo desde el
                // contenedor y un enum no es instanciable.
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label())
                    ->color(fn ($state) => $state?->color()),

                TextColumn::make('agent.name')
                    ->label('Atiende')
                    ->placeholder('Sin asignar'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(collect(RentalRequestStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()),

                SelectFilter::make('coverage')
                    ->label('Cobertura')
                    ->options(collect(RentalCoverage::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()),

                SelectFilter::make('assigned_to')
                    ->label('Agente')
                    ->options(fn () => User::whereIn('role', [UserRole::Sales, UserRole::Admin])
                        ->orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([
                Action::make('atender')
                    ->label('Atender')
                    ->icon('heroicon-m-phone-arrow-up-right')
                    ->schema([
                        Select::make('status')
                            ->label('Estatus')
                            ->options(collect(RentalRequestStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all())
                            ->default(fn (RentalRequest $record) => $record->status->value)
                            ->required(),

                        Select::make('assigned_to')
                            ->label('Agente')
                            ->options(fn () => User::whereIn('role', [UserRole::Sales, UserRole::Admin])
                                ->orderBy('name')->pluck('name', 'id'))
                            ->default(fn (RentalRequest $record) => $record->assigned_to ?? auth()->id())
                            ->searchable(),

                        Textarea::make('internal_notes')
                            ->label('Notas internas')
                            ->rows(3)
                            ->default(fn (RentalRequest $record) => $record->internal_notes),
                    ])
                    ->action(function (RentalRequest $record, array $data) {
                        $record->update([
                            ...$data,
                            // El primer contacto sella su fecha una sola vez
                            'contacted_at' => $record->contacted_at
                                ?? ($data['status'] !== RentalRequestStatus::New->value ? now() : null),
                        ]);

                        Notification::make()->success()->title("{$record->folio} actualizado")->send();
                    }),

                EditAction::make()->label('Ver detalle'),
            ]);
    }
}
