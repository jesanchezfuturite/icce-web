<?php

namespace App\Models;

use App\Enums\RentalCoverage;
use App\Enums\RentalRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'folio', 'product_id', 'equipment_name', 'client_name', 'company', 'email', 'phone',
    'location', 'coverage', 'start_date', 'rental_days', 'project_description', 'notes',
    'status', 'assigned_to', 'contacted_at', 'internal_notes',
])]
class RentalRequest extends Model
{
    protected function casts(): array
    {
        return [
            'coverage' => RentalCoverage::class,
            'status' => RentalRequestStatus::class,
            'start_date' => 'date',
            'contacted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Folio del lead de renta: RNT-2026-00007. */
    public static function nextFolio(): string
    {
        $prefix = 'RNT-'.now()->year.'-';

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
}
