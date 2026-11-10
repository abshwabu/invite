<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Template;
use App\Services\DesignUpgrader;
use App\Services\DesignValidator;
use App\Services\EntitlementService;
use App\Templates\Palettes\PaletteLibrary;
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

        $rawDesign = $template?->design_json ?? DesignValidator::blankDesign();
        $design = DesignUpgrader::upgrade($rawDesign);

        $categorySlug = $template?->category?->slug ?? 'wedding';
        $curatedPalettes = PaletteLibrary::curatedForCategory($categorySlug, 12);
        $allPalettes = PaletteLibrary::all();
        $initialTemplateDesign = $template?->design_json ? DesignUpgrader::upgrade($template->design_json) : null;

        return Inertia::render('Editor/Show', [
            'event' => null,
            'target' => $template?->kind === 'thank_you' ? 'thank_you' : 'invitation',
            'design' => $design,
            'template' => $template,
            'initialTemplateDesign' => $initialTemplateDesign,
            'categories' => $categories,
            'curatedPalettes' => $curatedPalettes,
            'allPalettes' => $allPalettes,
            'cardLanguage' => 'en',
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
