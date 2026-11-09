<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;

class AudienceService
{
    /**
     * Resolve eligible guests and breakdown statistics based on audience filter and channel.
     *
     * @return array{
     *     eligible_guests: Collection,
     *     stats: array{
     *         total_audience: int,
     *         opted_out_excluded: int,
     *         missing_contact_excluded: int,
     *         eligible_recipients: int,
     *         sms_count: int,
     *         email_count: int
     *     }
     * }
     */
    public function resolve(Event $event, string $audienceType, array $guestIds = [], string $channel = 'sms'): array
    {
        $query = $event->guests();

        switch ($audienceType) {
            case 'rsvp_pending':
                $query->where('rsvp_status', 'pending');
                break;
            case 'rsvp_yes':
                $query->where('rsvp_status', 'yes');
                break;
            case 'rsvp_no':
                $query->where('rsvp_status', 'no');
                break;
            case 'rsvp_maybe':
                $query->where('rsvp_status', 'maybe');
                break;
            case 'checked_in':
                $query->whereNotNull('checked_in_at');
                break;
            case 'not_checked_in':
                $query->whereNull('checked_in_at');
                break;
            case 'not_viewed':
                $query->whereNull('viewed_at');
                break;
            case 'failed_deliveries':
                $failedGuestIds = Delivery::whereHas('campaign', fn ($q) => $q->where('event_id', $event->id))
                    ->whereIn('status', ['failed', 'bounced'])
                    ->pluck('guest_id')
                    ->unique();
                $query->whereIn('id', $failedGuestIds);
                break;
            case 'manual':
                $query->whereIn('id', $guestIds);
                break;
            case 'all':
            default:
                // No additional filter
                break;
        }

        $allMatching = $query->get();
        $totalAudience = $allMatching->count();

        // 1. Exclude opted-out guests
        $notOptedOut = $allMatching->filter(fn ($g) => ! $g->opted_out);
        $optedOutCount = $totalAudience - $notOptedOut->count();

        // 2. Exclude guests missing contact for the chosen channel
        $eligible = $notOptedOut->filter(function ($g) use ($channel) {
            $hasPhone = ! empty(trim((string) $g->phone));
            $hasEmail = ! empty(trim((string) $g->email));

            if ($channel === 'sms') {
                return $hasPhone;
            }
            if ($channel === 'email') {
                return $hasEmail;
            }
            return $hasPhone || $hasEmail;
        });

        $missingContactCount = $notOptedOut->count() - $eligible->count();

        // Count expected SMS and Email dispatches
        $smsCount = 0;
        $emailCount = 0;

        foreach ($eligible as $guest) {
            $hasPhone = ! empty(trim((string) $guest->phone));
            $hasEmail = ! empty(trim((string) $guest->email));

            if ($channel === 'sms') {
                $smsCount++;
            } elseif ($channel === 'email') {
                $emailCount++;
            } else {
                if ($hasPhone) $smsCount++;
                if ($hasEmail) $emailCount++;
            }
        }

        return [
            'eligible_guests' => $eligible->values(),
            'stats' => [
                'total_audience' => $totalAudience,
                'opted_out_excluded' => $optedOutCount,
                'missing_contact_excluded' => $missingContactCount,
                'eligible_recipients' => $eligible->count(),
                'sms_count' => $smsCount,
                'email_count' => $emailCount,
            ],
        ];
    }
}
