<?php

namespace App\Filament\Resources\UrlRedirects;

use App\Filament\Resources\UrlRedirects\Pages\CreateUrlRedirect;
use App\Filament\Resources\UrlRedirects\Pages\EditUrlRedirect;
use App\Filament\Resources\UrlRedirects\Pages\ListUrlRedirects;
use App\Filament\Resources\UrlRedirects\Schemas\UrlRedirectForm;
use App\Filament\Resources\UrlRedirects\Tables\UrlRedirectsTable;
use App\Models\UrlRedirect;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class UrlRedirectResource extends Resource
{
    protected static ?string $model = UrlRedirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?string $navigationLabel = 'Redirecciones SEO';

    protected static ?string $modelLabel = 'redirección';

    protected static ?string $pluralModelLabel = 'redirecciones';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordRouteKeyName = 'id';

    public static function form(Schema $schema): Schema
    {
        return UrlRedirectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UrlRedirectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUrlRedirects::route('/'),
            'create' => CreateUrlRedirect::route('/create'),
            'edit' => EditUrlRedirect::route('/{record}/edit'),
        ];
    }
}
