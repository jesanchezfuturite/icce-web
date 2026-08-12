<?php

namespace App\Filament\Resources\ContactMessages\Tables;

use App\Enums\ContactStatus;
use App\Enums\UserRole;
use App\Models\ContactMessage;
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

/** Bandeja del formulario general (6.1). */
class ContactMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('agent'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Recibido')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (ContactMessage $r) => $r->created_at->diffForHumans())
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Quién escribe')
                    ->description(fn (ContactMessage $r) => $r->company)
                    ->searchable(['name', 'company', 'email'])
                    ->wrap(),

                TextColumn::make('subject')
                    ->label('Asunto')
                    ->badge()
                    ->searchable(),

                TextColumn::make('message')
                    ->label('Mensaje')
                    ->limit(70)
                    ->wrap()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->options(collect(ContactStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()),

                SelectFilter::make('subject')
                    ->label('Asunto')
                    ->options(fn () => ContactMessage::distinct()->pluck('subject', 'subject')->all()),
            ])
            ->recordActions([
                Action::make('atender')
                    ->label('Atender')
                    ->icon('heroicon-m-inbox-arrow-down')
                    ->schema([
                        Select::make('status')
                            ->label('Estatus')
                            ->options(collect(ContactStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all())
                            ->default(fn (ContactMessage $record) => $record->status->value)
                            ->required(),

                        Select::make('assigned_to')
                            ->label('Quién lo atiende')
                            ->options(fn () => User::whereIn('role', [UserRole::Sales, UserRole::Admin])
                                ->orderBy('name')->pluck('name', 'id'))
                            ->default(fn (ContactMessage $record) => $record->assigned_to ?? auth()->id())
                            ->searchable(),

                        Textarea::make('internal_notes')
                            ->label('Notas internas')
                            ->rows(3)
                            ->default(fn (ContactMessage $record) => $record->internal_notes),
                    ])
                    ->action(function (ContactMessage $record, array $data) {
                        $record->update([
                            ...$data,
                            // La fecha de atención se sella una sola vez
                            'handled_at' => $record->handled_at
                                ?? ($data['status'] !== ContactStatus::New->value ? now() : null),
                        ]);

                        Notification::make()->success()->title('Mensaje actualizado')->send();
                    }),

                Action::make('responder')
                    ->label('Responder')
                    ->icon('heroicon-m-envelope')
                    ->color('gray')
                    ->url(fn (ContactMessage $record) => 'mailto:'.$record->email
                        .'?subject='.rawurlencode('Re: '.$record->subject.' — ICCE Rentas y Servicios')),

                EditAction::make()->label('Ver detalle'),
            ])
            ->emptyStateHeading('Sin mensajes')
            ->emptyStateDescription('Aquí llegan los envíos del formulario de contacto del sitio.');
    }
}
