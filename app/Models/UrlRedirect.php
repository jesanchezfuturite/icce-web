<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['old_path', 'new_path', 'status_code', 'is_active', 'hits', 'last_hit_at'])]
class UrlRedirect extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }

    /** Normaliza una ruta entrante para compararla contra `old_path`. */
    public static function normalizePath(string $path): string
    {
        return '/'.trim(rawurldecode($path), '/');
    }

    /**
     * Un solo UPDATE atómico: `incrementQuietly` sólo escribe el contador y
     * descarta los demás atributos sucios, así que la fecha se perdía. Se hace
     * sobre el query base para no mover `updated_at` en cada visita.
     */
    public function registerHit(): void
    {
        $now = now();

        $this->newQuery()->toBase()->where('id', $this->getKey())->update([
            'hits' => DB::raw('hits + 1'),
            'last_hit_at' => $now,
        ]);

        $this->forceFill(['hits' => $this->hits + 1, 'last_hit_at' => $now])->syncOriginal();
    }
}
