<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    /**
     * Display template gallery with categories.
     */
    public function index(Request $request): Response
    {
        $categories = Category::with([
            'templates' => fn ($query) => $query->where('is_active', true)->where('is_system', true)->orderBy('sort'),
        ])->orderBy('sort')->get();

        return Inertia::render('Templates/Index', [
            'categories' => $categories,
        ]);
    }
}
