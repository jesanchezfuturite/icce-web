<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.home', [
            'banners' => Banner::live()->get(),
            'stats' => [
                ['1992', 'Año de fundación'],
                [Product::active()->count().'+', 'Productos en catálogo'],
                ['Nacional', 'Cobertura de renta'],
                [Brand::count().' marcas', 'Distribución autorizada'],
            ],
            'categories' => Category::query()
                ->roots()
                ->active()
                ->where('slug', '!=', 'renta-de-equipos')
                ->with('children')
                ->orderBy('sort_order')
                ->get(),
            'rentalCategory' => Category::where('slug', 'renta-de-equipos')
                ->with('children')
                ->first(),
            'brands' => Brand::featured()->whereNotNull('logo_path')->get(),
            'projects' => Project::featured()->take(3)->get(),
            'posts' => Post::published()->with('author')->take(3)->get(),
            // "Sale de almacén hoy": sólo lo que realmente se puede cobrar en línea
            'featured' => Product::query()
                ->active()
                ->forSale()
                ->where('is_on_demand', false)
                ->where('stock_qty', '>', 0)
                ->whereHas('images')
                ->with(['brand', 'primaryImage'])
                ->orderByDesc('is_featured')
                ->orderByDesc('stock_qty')
                ->take(4)
                ->get(),
        ]);
    }
}
