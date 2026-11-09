<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\User;
use App\Services\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EventMemberController extends Controller
{
    public function __construct(
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Invite a co-host to an event.
     */
    public function store(Request $request, Event $event): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageMembers', $event);

        $user = $request->user();

        // Check co_hosts feature entitlement
        if (! $this->entitlementService->can($user, 'co_hosts')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Co-hosts are not included in your current plan. Please upgrade to invite collaborators.'),
                ], 403);
            }
            return back()->with('error', __('Co-hosts are not included in your current plan. Please upgrade to invite collaborators.'));
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'string', 'in:editor,viewer'],
        ]);

        $invitedUser = User::where('email', strtolower($validated['email']))->first();
        if (! $invitedUser) {
            throw ValidationException::withMessages([
                'email' => __('No user found with this email address. Please ask them to register first.'),
            ]);
        }

        if ($invitedUser->id === $event->user_id) {
            throw ValidationException::withMessages([
                'email' => __('You are already the owner of this event.'),
            ]);
        }

        $member = EventMember::updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $invitedUser->id,
            ],
            [
                'role' => $validated['role'],
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'member' => $member->load('user'),
                'message' => __(':name was invited as a :role.', ['name' => $invitedUser->name, 'role' => $validated['role']]),
            ]);
        }

        return back()->with('success', __(':name was invited as a :role.', ['name' => $invitedUser->name, 'role' => $validated['role']]));
    }

    /**
     * Remove a co-host from an event.
     */
    public function destroy(Request $request, Event $event, User $user): JsonResponse|RedirectResponse
    {
        Gate::authorize('manageMembers', $event);

        $event->members()->where('user_id', $user->id)->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('Co-host removed successfully.'),
            ]);
        }

        return back()->with('success', __('Co-host removed successfully.'));
    }
}
