<?php

namespace App\Models;

use App\Enums\ContactStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name', 'company', 'email', 'phone', 'location', 'subject', 'message',
    'status', 'assigned_to', 'handled_at', 'internal_notes', 'ip_address',
])]
class ContactMessage extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
