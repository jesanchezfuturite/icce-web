<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'phone', 'company', 'rfc', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Órdenes que este agente tiene asignadas en el CRM. */
    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_to');
    }

    /** @return HasMany<RentalRequest, $this> */
    public function assignedRentalRequests(): HasMany
    {
        return $this->hasMany(RentalRequest::class, 'assigned_to');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->role->canAccessAdminPanel();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
