<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')
                    ->disk('site')
                    ->label('Portada')
                    ->height(40),

                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->limit(70),

                TextColumn::make('topic')
                    ->label('Tema')
                    ->badge()
                    ->searchable(),

                TextColumn::make('author.name')
                    ->label('Autor')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('published_at')
                    ->label('Publicado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Borrador'),

                IconColumn::make('is_featured')
                    ->label('Destacado')
                    ->boolean(),

                TextColumn::make('reading_minutes')
                    ->label('Min.')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('slug')
                    ->label('URL')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('topic')
                    ->label('Tema')
                    ->options(fn () => Post::whereNotNull('topic')->distinct()->pluck('topic', 'topic')->all()),

                Filter::make('drafts')
                    ->label('Sólo borradores')
                    ->query(fn (Builder $query) => $query->where(
                        fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '>', now()),
                    )),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
