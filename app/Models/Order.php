<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'folio', 'user_id', 'assigned_to', 'order_type', 'status',
    'customer_name', 'customer_email', 'customer_phone', 'customer_company',
    'subtotal', 'discount_amount', 'tax_amount', 'shipping_amount', 'total_amount', 'currency',
    'shipping_address', 'billing_address',
    'estimated_delivery_date', 'tracking_number', 'carrier',
    'payment_provider', 'payment_reference', 'payment_status',
    'customer_notes', 'internal_notes', 'quote_valid_until',
    'quoted_at', 'paid_at', 'shipped_at', 'delivered_at', 'cancelled_at',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'estimated_delivery_date' => 'date',
            'quote_valid_until' => 'date',
            'quoted_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'folio';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Agente de ventas responsable en el CRM. */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    /**
     * Genera el siguiente folio del año para el tipo dado (VD-2026-00014).
     * El bloqueo evita que dos checkouts simultáneos tomen el mismo número.
     */
    public static function nextFolio(OrderType $type): string
    {
        $year = now()->year;
        $prefix = "{$type->folioPrefix()}-{$year}-";

        return DB::transaction(function () use ($prefix) {
            $last = static::query()
                ->where('folio', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('folio')
                ->value('folio');

            $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });
    }

    public function scopeQuotes(Builder $query): Builder
    {
        return $query->where('order_type', OrderType::Quote);
    }

    public function scopeSales(Builder $query): Builder
    {
        return $query->where('order_type', OrderType::DirectSale);
    }
}
