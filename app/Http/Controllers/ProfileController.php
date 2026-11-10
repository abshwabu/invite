<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'hasPassword' => $request->user()->password !== null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->locale) {
            session(['locale' => $user->locale]);
            App::setLocale($user->locale);
        }

        return Redirect::route('profile.edit')
            ->withCookie(cookie()->forever('locale', $user->locale));
    }

    /**
     * Delete the user's account and associated data.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->password !== null) {
            $request->validate([
                'password' => ['required', 'current_password'],
            ]);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Export all personal user data (GDPR / Privacy compliance).
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();

        $data = [
            'export_date' => now()->toIso8601String(),
            'platform' => config('app.name', 'Invite'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale,
                'country' => $user->country,
                'registered_ip' => $user->registered_ip,
                'registration_source' => $user->registration_source,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'events' => $user->events()->with(['category:id,name_en,name_am,slug'])->get()->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'description' => $e->description,
                'status' => $e->status,
                'starts_at' => $e->starts_at?->toIso8601String(),
                'ends_at' => $e->ends_at?->toIso8601String(),
                'venue_name' => $e->venue_name,
                'venue_address' => $e->venue_address,
                'host_names' => $e->host_names,
                'category' => $e->category?->name_en,
                'created_at' => $e->created_at->toIso8601String(),
            ]),
            'guests' => \App\Models\Guest::whereIn('event_id', $user->events()->pluck('id'))->get()->map(fn ($g) => [
                'id' => $g->id,
                'event_id' => $g->event_id,
                'name' => $g->name,
                'phone' => $g->phone,
                'email' => $g->email,
                'rsvp_status' => $g->rsvp_status,
                'rsvp_party_size' => $g->rsvp_party_size,
                'checked_in_at' => $g->checked_in_at?->toIso8601String(),
                'created_at' => $g->created_at->toIso8601String(),
            ]),
            'subscriptions' => $user->subscriptions()->with('tier:id,name_en,slug')->get()->map(fn ($s) => [
                'id' => $s->id,
                'tier' => $s->tier?->name_en,
                'cards_total' => $s->cards_total,
                'cards_used' => $s->cards_used,
                'status' => $s->status,
                'starts_at' => $s->starts_at->toIso8601String(),
                'expires_at' => $s->expires_at?->toIso8601String(),
            ]),
            'usage_history' => $user->usageLedgers()->latest('id')->take(100)->get()->map(fn ($u) => [
                'id' => $u->id,
                'quantity' => $u->quantity,
                'kind' => $u->kind,
                'channel' => $u->channel,
                'note' => $u->note,
                'created_at' => $u->created_at->toIso8601String(),
            ]),
            'payments' => $user->payments()->latest('id')->get()->map(fn ($p) => [
                'id' => $p->id,
                'payment_code' => $p->payment_code,
                'status' => $p->status,
                'amount_expected' => (float) $p->amount_expected,
                'amount_verified' => (float) $p->amount_verified,
                'reference_number' => $p->reference_number,
                'submitted_at' => $p->submitted_at?->toIso8601String(),
                'created_at' => $p->created_at->toIso8601String(),
            ]),
        ];

        $filename = "invite-data-export-user-{$user->id}-" . now()->format('YmdHis') . ".json";

        return response()->streamDownload(
            fn () => print(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            $filename,
            ['Content-Type' => 'application/json']
        );
    }
}
