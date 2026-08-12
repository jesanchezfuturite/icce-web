<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Project;
use Illuminate\View\View;

class PageController extends Controller
{
    public function empresa(): View
    {
        return view('pages.empresa', [
            'projects' => Project::featured()->take(3)->get(),
            'brands' => Brand::featured()->whereNotNull('logo_path')->get(),
        ]);
    }

    public function servicios(): View
    {
        return view('pages.servicios');
    }

    public function contacto(): View
    {
        return view('pages.contacto');
    }

    public function privacidad(): View
    {
        return view('pages.legal', [
            'title' => 'Aviso de privacidad',
            'lead' => 'Cómo tratamos los datos personales que nos compartes.',
        ]);
    }

    public function politicas(): View
    {
        return view('pages.legal', [
            'title' => 'Políticas de la empresa',
            'lead' => 'Condiciones de venta, renta, entrega y garantía.',
        ]);
    }
}
