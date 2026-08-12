<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Recupera las fichas técnicas del fabricante desde el sitio actual.
 *
 * Los PDF pesan ~88 MB y no se versionan (ver .gitignore). Este comando los
 * repone en un entorno nuevo a partir del mapa que sí está en el repositorio,
 * database/data/icce_datasheets.json. En producción el destino final es S3.
 */
class FetchFichasCommand extends Command
{
    protected $signature = 'icce:fetch-fichas
                            {--base=https://icce.com.mx : Origen desde donde descargar}
                            {--force : Vuelve a descargar las que ya existen}';

    protected $description = 'Descarga las fichas técnicas en PDF a public/fichas';

    public function handle(): int
    {
        $mapFile = database_path('data/icce_datasheets.json');

        if (! file_exists($mapFile)) {
            $this->error("No existe {$mapFile}.");

            return self::FAILURE;
        }

        $remotePaths = collect(json_decode(file_get_contents($mapFile), true))
            ->flatten()
            ->unique()
            ->values();

        $destination = public_path('fichas');

        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $base = rtrim((string) $this->option('base'), '/');
        $downloaded = $skipped = $failed = 0;

        $bar = $this->output->createProgressBar($remotePaths->count());
        $bar->start();

        foreach ($remotePaths as $remote) {
            $file = $destination.'/'.self::flatten($remote);

            if (file_exists($file) && ! $this->option('force')) {
                $skipped++;
                $bar->advance();

                continue;
            }

            try {
                $response = Http::timeout(30)
                    ->withHeaders(['User-Agent' => 'ICCE-Deploy/1.0'])
                    ->get($base.'/'.str_replace(' ', '%20', $remote));

                if ($response->successful() && $response->body() !== '') {
                    file_put_contents($file, $response->body());
                    $downloaded++;
                } else {
                    $failed++;
                }
            } catch (\Throwable) {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Descargadas {$downloaded} · ya presentes {$skipped} · fallidas {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Aplana la ruta remota al nombre con el que se guarda en public/fichas. */
    public static function flatten(string $remote): string
    {
        $name = preg_replace('#^Fichas-Tecnicas/#', '', $remote);

        return str_replace([' ', '/'], ['-', '_'], $name);
    }
}
