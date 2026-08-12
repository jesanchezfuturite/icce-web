<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        return view('pages.proyectos.index', [
            'projects' => Project::orderBy('sort_order')->paginate(9),
        ]);
    }

    public function show(Project $project): View
    {
        return view('pages.proyectos.show', [
            'project' => $project,
            'related' => Project::where('id', '!=', $project->id)->take(3)->get(),
        ]);
    }
}
