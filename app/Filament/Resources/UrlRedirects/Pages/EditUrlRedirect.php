<?php

namespace App\Filament\Resources\UrlRedirects\Pages;

use App\Filament\Resources\UrlRedirects\UrlRedirectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUrlRedirect extends EditRecord
{
    protected static string $resource = UrlRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
