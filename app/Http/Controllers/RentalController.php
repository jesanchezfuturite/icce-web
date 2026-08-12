<?php

namespace App\Http\Controllers;

use App\Enums\RentalCoverage;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Catálogo informativo de renta (REQ-06). Sin motor de pago: el objetivo es
 * captar el lead con el formulario adaptativo (REQ-07, fase 4).
 */
class RentalController extends Controller
{
    public function index(): View
    {
        $equipment = Product::query()
            ->active()
            ->rentals()
            ->with(['brand', 'category', 'primaryImage'])
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Product $product) => $product->category->name);

        return view('pages.renta.index', [
            'categories' => Category::where('slug', 'renta-de-equipos')
                ->with('children')
                ->first()?->children ?? collect(),
            'equipmentByCategory' => $equipment,
            'nationalCount' => Product::rentals()->active()->where('rental_coverage', RentalCoverage::National)->count(),
            'localCount' => Product::rentals()->active()->where('rental_coverage', RentalCoverage::Local)->count(),
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active && $product->is_rental, 404);

        return view('pages.renta.equipo', [
            'product' => $product->load('brand', 'category', 'images'),
            'related' => Product::query()
                ->active()
                ->rentals()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->with('primaryImage')
                ->take(3)
                ->get(),
        ]);
    }

    public function requisitos(): View
    {
        return view('pages.renta.requisitos');
    }

    /** REQ-07: formulario adaptativo, precargable desde la ficha del equipo. */
    public function solicitar(): View
    {
        return view('pages.renta.solicitar');
    }
}
