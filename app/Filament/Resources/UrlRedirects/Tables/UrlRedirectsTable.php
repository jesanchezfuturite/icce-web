<?php

namespace App\Filament\Resources\UrlRedirects\Tables;

use App\Models\UrlRedirect;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Mapa de migración SEO en el backoffice (TRD 4.3).
 *
 * Sirve para dos cosas durante el cambio de dominio: cerrar las rutas que
 * quedaron sin destino, y ver cuáles siguen recibiendo visitas —que es la señal
 * de qué enlaces externos hay que pedir que se actualicen.
 */
class UrlRedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('old_path')
                    ->label('Ruta anterior')
                    ->searchable()
                    ->wrap()
                    ->limit(70)
                    ->copyable(),

                TextColumn::make('new_path')
                    ->label('Destino')
                    ->searchable()
                    ->placeholder('— sin mapear —')
                    ->wrap()
                    ->limit(50)
                    ->color(fn (UrlRedirect $r) => $r->new_path === null ? 'danger' : null),

                TextColumn::make('status_code')
                    ->label('Código')
                    ->badge()
                    ->color(fn ($state) => $state === 410 ? 'gray' : 'success')
                    ->formatStateUsing(fn ($state) => $state === 410 ? '410 Gone' : '301'),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),

                TextColumn::make('hits')
                    ->label('Visitas')
                    ->sortable()
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('last_hit_at')
                    ->label('Último acceso')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca')
                    ->sortable(),
            ])
            ->defaultSort('hits', 'desc')
            ->filters([
                Filter::make('pendientes')
                    ->label('Pendientes de mapeo')
                    ->query(fn (Builder $query) => $query->whereNull('new_path')->where('status_code', 301)),

                Filter::make('con_trafico')
                    ->label('Con visitas registradas')
                    ->query(fn (Builder $query) => $query->where('hits', '>', 0)),

                SelectFilter::make('status_code')
                    ->label('Código')
                    ->options([301 => '301 Permanente', 410 => '410 Gone']),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkTargetAction(),
                    self::bulkGoneAction(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin redirecciones')
            ->emptyStateDescription('El mapa se siembra desde el rastreo del sitio anterior.');
    }

    /** Cierra varias rutas huérfanas apuntándolas al mismo destino. */
    private static function bulkTargetAction(): BulkAction
    {
        return BulkAction::make('destinoMasivo')
            ->label('Asignar destino')
            ->icon('heroicon-m-arrow-right-circle')
            ->schema([
                TextInput::make('new_path')
                    ->label('Destino')
                    ->required()
                    ->placeholder('/catalogo/llanas')
                    ->helperText('Ruta relativa dentro del sitio nuevo.'),
            ])
            ->action(function (Collection $records, array $data) {
                UrlRedirect::whereKey($records->pluck('id'))->update([
                    'new_path' => '/'.ltrim($data['new_path'], '/'),
                    'status_code' => 301,
                    'is_active' => true,
                ]);

                Notification::make()
                    ->success()
                    ->title('Destino asignado')
                    ->body("{$records->count()} rutas ahora redirigen.")
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function bulkGoneAction(): BulkAction
    {
        return BulkAction::make('marcarGone')
            ->label('Marcar como retiradas (410)')
            ->icon('heroicon-m-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Un 410 le dice al buscador que retire la ruta del índice en lugar de reintentarla. Úsalo en páginas que no deben heredar autoridad a ningún destino.')
            ->action(function (Collection $records) {
                UrlRedirect::whereKey($records->pluck('id'))->update([
                    'new_path' => null,
                    'status_code' => 410,
                    'is_active' => true,
                ]);

                Notification::make()->success()->title('Rutas retiradas')->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
