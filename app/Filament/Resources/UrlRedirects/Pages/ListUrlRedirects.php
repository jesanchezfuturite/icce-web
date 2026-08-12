<?php

namespace App\Filament\Resources\UrlRedirects\Pages;

use App\Filament\Resources\UrlRedirects\UrlRedirectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUrlRedirects extends ListRecords
{
    protected static string $resource = UrlRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
