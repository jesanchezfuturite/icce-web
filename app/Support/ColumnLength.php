<?php

namespace App\Support;

use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Largo máximo de una columna de texto de la base de datos.
 *
 * Los formularios del backoffice lo usan para limitar cada campo al largo real
 * de su columna: sin eso, un texto de 440 caracteres en una columna de 255 no
 * se rechaza en el formulario y la base lo corta con un error 500.
 */
final class ColumnLength
{
    /** @var array<string, array<string, int|null>> tabla => columna => largo */
    private static array $cache = [];

    /** Largo que cabe en un tipo SQL: `varchar(255)` → 255, `text` → 65535, `json` → null. */
    public static function parse(string $type): ?int
    {
        $type = strtolower(trim($type));

        if (preg_match('/^(?:var)?char\((\d+)\)/', $type, $m)) {
            return (int) $m[1];
        }

        return match (true) {
            str_starts_with($type, 'tinytext') => 255,
            str_starts_with($type, 'mediumtext'), str_starts_with($type, 'longtext') => null,   // más de lo que un formulario manda
            str_starts_with($type, 'text') => 65535,
            default => null,
        };
    }

    public static function for(string $table, string $column): ?int
    {
        if (! isset(self::$cache[$table])) {
            self::$cache[$table] = [];

            foreach (Schema::getColumns($table) as $info) {
                self::$cache[$table][$info['name']] = self::parse($info['type']);
            }
        }

        return self::$cache[$table][$column] ?? null;
    }

    /**
     * Límite para un campo de Filament, o null si no aplica: campos anidados
     * (repetidores), formularios sin modelo o columnas que no son de texto.
     */
    public static function forComponent(Component $component): ?int
    {
        $name = $component->getStatePath(isAbsolute: false);

        if (! $name || str_contains($name, '.')) {
            return null;
        }

        $model = $component->getModel();

        if (! $model || ! is_subclass_of($model, Model::class)) {
            return null;
        }

        return self::for((new $model)->getTable(), $name);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
