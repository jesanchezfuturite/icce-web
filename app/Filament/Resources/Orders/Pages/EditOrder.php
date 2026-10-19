<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Orders\RecalculateQuote;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Al guardar precios cotizados o descuento se recalculan y guardan los
     * importes de la orden, con la misma fórmula que se vio en pantalla.
     */
    protected function afterSave(): void
    {
        $order = $this->getRecord();

        if ($order->pricesAreEditable()) {
            app(RecalculateQuote::class)($order);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
