<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Event;
use App\Models\Template;
use App\Models\User;

class EventDraftImporter
{
    /**
     * Import a guest draft event for an authenticated user.
     */
    public static function importForUser(User $user, ?array $draft): ?Event
    {
        if (empty($draft) || ! is_array($draft) || empty($draft['design_json'])) {
            return null;
        }

        $template = null;
        if (! empty($draft['template_id'])) {
            $template = Template::find($draft['template_id']);
        }

        $categoryId = $draft['category_id']
            ?? ($template?->category_id)
            ?? Category::where('slug', 'wedding')->value('id')
            ?? Category::first()?->id;

        return Event::create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'template_id' => $template?->id,
            'title' => $draft['title'] ?? ($template ? $template->name . ' Invitation' : 'My Event Invitation'),
            'description' => $draft['description'] ?? null,
            'starts_at' => ! empty($draft['starts_at']) ? $draft['starts_at'] : now()->addDays(7)->setTime(14, 0),
            'ends_at' => ! empty($draft['ends_at']) ? $draft['ends_at'] : now()->addDays(7)->setTime(20, 0),
            'timezone' => $draft['timezone'] ?? 'Africa/Addis_Ababa',
            'venue_name' => $draft['venue_name'] ?? null,
            'venue_address' => $draft['venue_address'] ?? null,
            'map_url' => $draft['map_url'] ?? null,
            'host_names' => $draft['host_names'] ?? null,
            'is_invite_only' => $draft['is_invite_only'] ?? true,
            'rsvp_enabled' => $draft['rsvp_enabled'] ?? true,
            'plus_ones_allowed' => $draft['plus_ones_allowed'] ?? false,
            'design_json' => $draft['design_json'],
            'status' => 'draft',
        ]);
    }
}
