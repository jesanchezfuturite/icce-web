<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Listados básicos del catálogo. Los filtros por marca/atributo, la búsqueda
 * y la ficha completa con galería y carrito llegan en la fase 3.
 */
class CatalogController extends Controller
{
    public function index(): View
    {
        return view('pages.catalogo.index', [
            'categories' => Category::query()
                ->roots()
                ->active()
                ->where('slug', '!=', 'renta-de-equipos')
                ->with(['children' => fn ($q) => $q->where('is_active', true)])
                ->withCount('products')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function category(Category $category): View
    {
        // El listado, los filtros y la paginación los resuelve el componente
        // Livewire `catalogo.explorador`, que además sincroniza su estado con
        // la URL para que un resultado filtrado sea compartible e indexable.
        return view('pages.catalogo.categoria', [
            'category' => $category->load('parent', 'children'),
        ]);
    }

    public function show(Product $product): View
    {
        // Un equipo de renta vive sólo en /renta/{slug}: servirlo también aquí
        // crearía contenido duplicado y competiría consigo mismo en el índice.
        abort_unless($product->is_active && ! $product->is_rental, 404);

        return view('pages.catalogo.producto', [
            'product' => $product->load('brand', 'category.parent', 'images'),
            'related' => Product::query()
                ->active()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->with(['brand', 'primaryImage'])
                ->take(4)
                ->get(),
        ]);
    }
}
