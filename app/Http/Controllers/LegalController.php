<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    /**
     * Display Terms of Service.
     */
    public function terms(Request $request): Response
    {
        return Inertia::render('Legal/Terms', [
            'appName' => config('app.name', 'Invite'),
        ]);
    }

    /**
     * Display Privacy Policy.
     */
    public function privacy(Request $request): Response
    {
        return Inertia::render('Legal/Privacy', [
            'appName' => config('app.name', 'Invite'),
        ]);
    }
}
