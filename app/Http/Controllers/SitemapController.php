<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Sitemap generado del contenido publicado (RNF-03).
 *
 * Reemplaza al del sitio anterior, que declaraba una sola URL —la portada—
 * pese a tener 269 páginas: buena parte del catálogo no estaba siendo
 * descubierta por el buscador.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHours(6), fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        $urls = [];

        // Institucionales, con la prioridad relativa que les corresponde
        foreach ([
            ['/', '1.0', 'daily'],
            ['/catalogo', '0.9', 'daily'],
            ['/renta', '0.9', 'weekly'],
            ['/empresa', '0.6', 'monthly'],
            ['/servicios', '0.6', 'monthly'],
            ['/proyectos', '0.7', 'weekly'],
            ['/blog', '0.7', 'weekly'],
            ['/descargas', '0.6', 'weekly'],
            ['/marcas', '0.6', 'monthly'],
            ['/contacto', '0.5', 'monthly'],
            ['/renta/requisitos', '0.4', 'yearly'],
            ['/aviso-de-privacidad', '0.2', 'yearly'],
            ['/politicas', '0.2', 'yearly'],
        ] as [$path, $priority, $frequency]) {
            $urls[] = ['loc' => url($path), 'priority' => $priority, 'changefreq' => $frequency];
        }

        Category::active()->orderBy('id')->each(function (Category $category) use (&$urls) {
            $urls[] = [
                'loc' => url("/catalogo/{$category->slug}"),
                'lastmod' => $category->updated_at?->toAtomString(),
                'priority' => $category->parent_id === null ? '0.8' : '0.7',
                'changefreq' => 'weekly',
            ];
        });

        Product::active()->orderBy('id')->each(function (Product $product) use (&$urls) {
            $urls[] = [
                'loc' => url(($product->is_rental ? '/renta/' : '/producto/').$product->slug),
                'lastmod' => $product->updated_at?->toAtomString(),
                'priority' => '0.6',
                'changefreq' => 'weekly',
            ];
        });

        Post::published()->each(function (Post $post) use (&$urls) {
            $urls[] = [
                'loc' => url("/blog/{$post->slug}"),
                'lastmod' => $post->updated_at?->toAtomString(),
                'priority' => '0.6',
                'changefreq' => 'monthly',
            ];
        });

        Project::orderBy('id')->each(function (Project $project) use (&$urls) {
            $urls[] = [
                'loc' => url("/proyectos/{$project->slug}"),
                'lastmod' => $project->updated_at?->toAtomString(),
                'priority' => '0.5',
                'changefreq' => 'monthly',
            ];
        });

        Brand::orderBy('id')->each(function (Brand $brand) use (&$urls) {
            $urls[] = [
                'loc' => url("/marcas/{$brand->slug}"),
                'lastmod' => $brand->updated_at?->toAtomString(),
                'priority' => '0.5',
                'changefreq' => 'monthly',
            ];
        });

        $body = '';

        foreach ($urls as $url) {
            $body .= "  <url>\n    <loc>".e($url['loc'])."</loc>\n";

            if (! empty($url['lastmod'])) {
                $body .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            }

            $body .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $body .= "    <priority>{$url['priority']}</priority>\n  </url>\n";
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            .$body
            ."</urlset>\n";
    }
}
