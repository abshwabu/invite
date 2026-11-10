<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Services\DesignUpgrader;
use App\Services\DesignValidator;
use App\Services\EntitlementService;
use App\Templates\Palettes\PaletteLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EventDesignController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Show the invitation card editor for an event.
     */
    public function showInvitation(Request $request, Event $event): Response
    {
        Gate::authorize('update', $event);

        $rawDesign = (is_array($event->design_json) && DesignValidator::isValid($event->design_json))
            ? $event->design_json
            : ($event->template?->design_json ?? DesignValidator::blankDesign());
        $design = DesignUpgrader::upgrade($rawDesign);

        $hasRemoveBranding = $this->entitlementService->can($request->user(), 'remove_branding');

        $categories = Category::with([
            'templates' => fn ($query) => $query->where('is_active', true)->where('kind', 'invitation')->orderBy('sort'),
        ])->orderBy('sort')->get();

        $categorySlug = $event->category?->slug ?? ($event->template?->category?->slug ?? 'wedding');
        $curatedPalettes = PaletteLibrary::curatedForCategory($categorySlug, 12);
        $allPalettes = PaletteLibrary::all();
        $initialTemplateDesign = $event->template?->design_json ? DesignUpgrader::upgrade($event->template->design_json) : null;

        return Inertia::render('Editor/Show', [
            'event' => $event->load(['category', 'template']),
            'target' => 'invitation',
            'design' => $design,
            'template' => $event->template,
            'initialTemplateDesign' => $initialTemplateDesign,
            'categories' => $categories,
            'curatedPalettes' => $curatedPalettes,
            'allPalettes' => $allPalettes,
            'cardLanguage' => $event->card_language ?? 'en',
            'hasRemoveBranding' => $hasRemoveBranding,
            'isGuest' => false,
            'eventData' => $this->formatEventData($event),
        ]);
    }

    /**
     * Update the invitation card design for an event.
     */
    public function updateInvitation(Request $request, Event $event): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $event);

        $validated = $request->validate([
            'design' => ['required', 'array'],
            'template_id' => ['nullable', 'exists:templates,id'],
            'card_language' => ['nullable', 'in:en,am,both'],
        ]);

        $normalized = DesignValidator::validate($validated['design'], autoUpgrade: true);

        $updateData = ['design_json' => $normalized];
        if (array_key_exists('template_id', $validated)) {
            $updateData['template_id'] = $validated['template_id'];
        }
        if (! empty($validated['card_language'])) {
            $updateData['card_language'] = $validated['card_language'];
        }

        $event->update($updateData);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => __('Invitation card saved successfully.'),
                'design' => $normalized,
            ]);
        }

        return back()->with('success', __('Invitation card saved successfully.'));
    }

    /**
     * Show the thank-you card editor for an event.
     */
    public function showThankYou(Request $request, Event $event): Response
    {
        Gate::authorize('updateThankYou', $event);

        $rawDesign = (is_array($event->thankyou_design_json) && DesignValidator::isValid($event->thankyou_design_json))
            ? $event->thankyou_design_json
            : ($event->thankyouTemplate?->design_json ?? DesignValidator::blankDesign());
        $design = DesignUpgrader::upgrade($rawDesign);

        $hasRemoveBranding = $this->entitlementService->can($request->user(), 'remove_branding');

        $categories = Category::with([
            'templates' => fn ($query) => $query->where('is_active', true)->where('kind', 'thank_you')->orderBy('sort'),
        ])->orderBy('sort')->get();

        $categorySlug = $event->category?->slug ?? ($event->thankyouTemplate?->category?->slug ?? 'wedding');
        $curatedPalettes = PaletteLibrary::curatedForCategory($categorySlug, 12);
        $allPalettes = PaletteLibrary::all();
        $initialTemplateDesign = $event->thankyouTemplate?->design_json ? DesignUpgrader::upgrade($event->thankyouTemplate->design_json) : null;

        return Inertia::render('Editor/Show', [
            'event' => $event->load(['category', 'thankyouTemplate']),
            'target' => 'thank_you',
            'design' => $design,
            'template' => $event->thankyouTemplate,
            'initialTemplateDesign' => $initialTemplateDesign,
            'categories' => $categories,
            'curatedPalettes' => $curatedPalettes,
            'allPalettes' => $allPalettes,
            'cardLanguage' => $event->card_language ?? 'en',
            'hasRemoveBranding' => $hasRemoveBranding,
            'isGuest' => false,
            'eventData' => $this->formatEventData($event),
        ]);
    }

    /**
     * Update the thank-you card design for an event.
     */
    public function updateThankYou(Request $request, Event $event): JsonResponse|RedirectResponse
    {
        Gate::authorize('updateThankYou', $event);

        $validated = $request->validate([
            'design' => ['required', 'array'],
            'template_id' => ['nullable', 'exists:templates,id'],
            'card_language' => ['nullable', 'in:en,am,both'],
        ]);

        $normalized = DesignValidator::validate($validated['design'], autoUpgrade: true);

        $updateData = ['thankyou_design_json' => $normalized];
        if (array_key_exists('template_id', $validated)) {
            $updateData['thankyou_template_id'] = $validated['template_id'];
        }
        if (! empty($validated['card_language'])) {
            $updateData['card_language'] = $validated['card_language'];
        }

        $event->update($updateData);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => __('Thank you card saved successfully.'),
                'design' => $normalized,
            ]);
        }

        return back()->with('success', __('Thank you card saved successfully.'));
    }

    /**
     * Upload an image layer asset.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:8192'],
        ]);

        $file = $request->file('image');
        $path = $file->store('user_uploads/' . $request->user()->id, 'public');

        return response()->json([
            'status' => 'success',
            'url' => Storage::url($path),
            'filename' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * Build preview replacement data from the event.
     */
    protected function formatEventData(Event $event): array
    {
        return [
            'guest_name' => 'Almaz Bekele',
            'event_title' => $event->title ?: 'Grand Celebration',
            'date' => $event->starts_at ? $event->starts_at->format('F j, Y') : 'Saturday, October 24, 2027',
            'time' => $event->starts_at ? $event->starts_at->format('g:i A') : '4:00 PM',
            'venue' => $event->venue_name ?: 'Sheraton Addis, Addis Ababa',
            'host_names' => $event->host_names ?: 'Abebe & Chaltu',
        ];
    }
}
