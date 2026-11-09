<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Services\EntitlementService;

class EventPolicy
{
    public function __construct(
        protected EntitlementService $entitlementService,
    ) {}

    /**
     * Determine whether the user can view the event design or details.
     */
    public function view(User $user, Event $event): bool
    {
        return $event->user_id === $user->id
            || $event->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can update the event design.
     */
    public function update(User $user, Event $event): bool
    {
        if ($event->user_id === $user->id) {
            return true;
        }

        return $event->members()
            ->where('user_id', $user->id)
            ->whereIn('role', ['editor', 'cohost', 'host'])
            ->exists();
    }

    /**
     * Determine whether the user can edit thank-you card design.
     */
    public function updateThankYou(User $user, Event $event): bool
    {
        if (! $this->update($user, $event)) {
            return false;
        }

        return $this->entitlementService->can($user, 'thank_you');
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, Event $event): bool
    {
        return $event->user_id === $user->id;
    }
}
