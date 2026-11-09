<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the user dashboard with their events.
     */
    public function index(Request $request): Response
    {
        $events = Event::where('user_id', $request->user()->id)
            ->with(['category', 'template'])
            ->withCount(['guests'])
            ->latest()
            ->get();

        return Inertia::render('Dashboard', [
            'events' => $events,
        ]);
    }
}
