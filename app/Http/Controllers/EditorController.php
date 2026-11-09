<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Template;
use App\Services\DesignValidator;
use App\Services\EntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EditorController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Show card editor for guests or authenticated users.
     */
    public function show(Request $request, ?Template $template = null): Response
    {
        if ($template) {
            $template->load('category');
        }

        $categories = Category::with([
            'templates' => fn ($query) => $query->where('is_active', true)->orderBy('sort'),
        ])->orderBy('sort')->get();

        $user = Auth::user();
        $hasRemoveBranding = $user ? $this->entitlementService->can($user, 'remove_branding') : false;

        $design = $template?->design_json ?? DesignValidator::blankDesign();

        return Inertia::render('Editor/Show', [
            'event' => null,
            'target' => $template?->kind === 'thank_you' ? 'thank_you' : 'invitation',
            'design' => $design,
            'template' => $template,
            'categories' => $categories,
            'hasRemoveBranding' => $hasRemoveBranding,
            'isGuest' => ! Auth::check(),
            'eventData' => [
                'guest_name' => 'Almaz Bekele',
                'event_title' => $template ? "{$template->name} Invitation" : 'Grand Celebration',
                'date' => 'Saturday, October 24, 2027',
                'time' => '4:00 PM',
                'venue' => 'Sheraton Addis, Addis Ababa',
                'host_names' => 'Abebe & Chaltu',
            ],
        ]);
    }
}
