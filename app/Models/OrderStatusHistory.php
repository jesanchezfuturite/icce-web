<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'user_id', 'from_status', 'to_status', 'note', 'notified_customer'])]
class OrderStatusHistory extends Model
{
    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
            'notified_customer' => 'boolean',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Quién hizo el cambio; null si lo disparó el sistema (webhook de pago). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
