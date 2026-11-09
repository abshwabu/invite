<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Services\EntitlementService;

class EventPolicy
{
    public function __construct(
        protected EntitlementService $entitlementService,
    ) {}

    public function viewAny(User|SuperAdmin $user): bool
    {
        return $user instanceof SuperAdmin;
    }

    /**
     * Determine whether the user can view the event design or details.
     */
    public function view(User|SuperAdmin $user, Event $event): bool
    {
        if ($user instanceof SuperAdmin) {
            return true;
        }

        return $event->user_id === $user->id
            || $event->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can update the event design.
     */
    public function update(User|SuperAdmin $user, Event $event): bool
    {
        if ($user instanceof SuperAdmin) {
            return $user->isOwner();
        }

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
    public function updateThankYou(User|SuperAdmin $user, Event $event): bool
    {
        if ($user instanceof SuperAdmin) {
            return $user->isOwner();
        }

        if (! $this->update($user, $event)) {
            return false;
        }

        return $this->entitlementService->can($user, 'thank_you');
    }

    /**
     * Determine whether the user can manage guests for the event.
     */
    public function manageGuests(User|SuperAdmin $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    /**
     * Determine whether the user can manage co-host members.
     */
    public function manageMembers(User|SuperAdmin $user, Event $event): bool
    {
        if ($user instanceof SuperAdmin) {
            return $user->isOwner();
        }

        return $event->user_id === $user->id;
    }

    /**
     * Determine whether the user can manage scanners for the event.
     */
    public function manageScanners(User|SuperAdmin $user, Event $event): bool
    {
        if ($user instanceof SuperAdmin) {
            return $user->isOwner();
        }

        return $event->user_id === $user->id;
    }

    /**
     * Determine whether the user can duplicate the event.
     */
    public function duplicate(User|SuperAdmin $user, Event $event): bool
    {
        if ($user instanceof SuperAdmin) {
            return $user->isOwner();
        }

        return $event->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User|SuperAdmin $user, Event $event): bool
    {
        if ($user instanceof SuperAdmin) {
            return $user->isOwner();
        }

        return $event->user_id === $user->id;
    }
}
