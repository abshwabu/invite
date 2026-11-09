<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EditorController extends Controller
{
    /**
     * Show card editor for guests or authenticated users.
     */
    public function show(Request $request, ?Template $template = null): Response
    {
        if ($template) {
            $template->load('category');
        }

        $categories = Category::orderBy('sort')->get();

        return Inertia::render('Editor/Show', [
            'template' => $template,
            'categories' => $categories,
            'isGuest' => ! Auth::check(),
        ]);
    }
}
