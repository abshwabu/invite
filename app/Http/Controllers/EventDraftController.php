<?php

namespace App\Http\Controllers;

use App\Services\EventDraftImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventDraftController extends Controller
{
    /**
     * Import a guest draft into the database for the authenticated user.
     */
    public function import(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'draft' => ['required', 'array'],
            'draft.design_json' => ['required', 'array'],
            'draft.template_id' => ['nullable', 'exists:templates,id'],
            'draft.title' => ['nullable', 'string', 'max:255'],
            'draft.venue_name' => ['nullable', 'string', 'max:255'],
            'draft.venue_address' => ['nullable', 'string', 'max:255'],
            'draft.host_names' => ['nullable', 'string', 'max:255'],
            'draft.starts_at' => ['nullable'],
        ]);

        $event = EventDraftImporter::importForUser($request->user(), $validated['draft']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'event_id' => $event?->id,
                'redirect' => route('dashboard'),
                'message' => __('Draft imported successfully.'),
            ]);
        }

        return redirect()->route('dashboard')->with('success', __('Draft imported successfully.'));
    }
}
