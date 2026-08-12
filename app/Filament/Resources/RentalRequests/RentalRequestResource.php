<?php

namespace App\Filament\Resources\RentalRequests;

use App\Filament\Resources\RentalRequests\Pages\CreateRentalRequest;
use App\Filament\Resources\RentalRequests\Pages\EditRentalRequest;
use App\Filament\Resources\RentalRequests\Pages\ListRentalRequests;
use App\Filament\Resources\RentalRequests\Schemas\RentalRequestForm;
use App\Filament\Resources\RentalRequests\Tables\RentalRequestsTable;
use App\Models\RentalRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RentalRequestResource extends Resource
{
    protected static ?string $model = RentalRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?string $navigationLabel = 'Solicitudes de renta';

    protected static ?string $modelLabel = 'solicitud de renta';

    protected static ?string $pluralModelLabel = 'solicitudes de renta';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordRouteKeyName = 'id';

    public static function form(Schema $schema): Schema
    {
        return RentalRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RentalRequestsTable::configure($table);
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
            'index' => ListRentalRequests::route('/'),
            'create' => CreateRentalRequest::route('/create'),
            'edit' => EditRentalRequest::route('/{record}/edit'),
        ];
    }
}
