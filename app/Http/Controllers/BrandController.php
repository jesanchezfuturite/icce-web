<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(): View
    {
        return view('pages.marcas.index', [
            'brands' => Brand::withCount('products')->orderByDesc('is_featured')->orderBy('sort_order')->get(),
        ]);
    }

    public function show(Brand $brand): View
    {
        return view('pages.marcas.show', [
            'brand' => $brand,
            'products' => $brand->products()
                ->active()
                ->with(['brand', 'category', 'primaryImage'])
                ->paginate(12),
        ]);
    }
}
