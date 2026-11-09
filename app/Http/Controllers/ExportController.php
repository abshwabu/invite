<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Event;
use App\Services\EthiopianCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Export complete guest list as CSV with RSVP, delivery status, and check-in status.
     */
    public function guestsCsv(Request $request, Event $event): StreamedResponse
    {
        Gate::authorize('view', $event);

        $campaignIds = $event->campaigns()->pluck('id');

        // Load guests with check-in scanner and latest delivery
        $guests = $event->guests()
            ->with(['checkedInByScanner'])
            ->orderBy('name')
            ->get();

        // Map latest delivery status per guest
        $deliveries = Delivery::whereIn('campaign_id', $campaignIds)
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('guest_id')
            ->map(fn ($group) => $group->first()?->status ?? 'none');

        $filename = sprintf(
            '%s-guests-%s.csv',
            Str::slug($event->title ?: 'event'),
            now()->format('Ymd')
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($guests, $deliveries) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel and Amharic character display
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Guest Name',
                'Phone',
                'Email',
                'Preferred Channel',
                'Party Size Allowed',
                'RSVP Status',
                'RSVP Attending Size',
                'RSVP Note',
                'Checked In',
                'Checked In At',
                'Checked In By',
                'Delivery Status',
                'Invitation Link',
            ]);

            foreach ($guests as $g) {
                $deliveryStatus = $deliveries[$g->id] ?? 'not_sent';

                fputcsv($handle, [
                    $g->name,
                    $g->phone ?: '',
                    $g->email ?: '',
                    $g->preferred_channel,
                    $g->max_party_size,
                    strtoupper($g->rsvp_status),
                    $g->rsvp_status === 'yes' ? ($g->rsvp_party_size ?: 1) : 0,
                    $g->rsvp_note ?: '',
                    $g->checked_in_at ? 'YES' : 'NO',
                    $g->checked_in_at ? $g->checked_in_at->toIso8601String() : '',
                    $g->checkedInByScanner?->name ?: '',
                    strtoupper($deliveryStatus),
                    route('public.invitation.show', ['token' => $g->token]),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Printable attendance sheet (HTML view formatted for paper print).
     */
    public function attendanceSheet(Request $request, Event $event): Response
    {
        Gate::authorize('view', $event);

        $guests = $event->guests()
            ->with('checkedInByScanner')
            ->orderBy('name')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'phone' => $g->phone,
                'max_party_size' => $g->max_party_size,
                'rsvp_status' => $g->rsvp_status,
                'rsvp_party_size' => $g->rsvp_party_size ?: 1,
                'checked_in' => (bool) $g->checked_in_at,
                'checked_in_at' => $g->checked_in_at?->format('H:i'),
                'scanner_name' => $g->checkedInByScanner?->name,
                'rsvp_note' => $g->rsvp_note,
            ]);

        $ethiopianDate = EthiopianCalendar::fromGregorian($event->starts_at);

        return Inertia::render('Events/Exports/AttendanceSheet', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at?->format('l, F j, Y • g:i A'),
                'ethiopian_date' => $ethiopianDate,
                'venue_name' => $event->venue_name,
                'venue_address' => $event->venue_address,
                'host_names' => $event->host_names,
            ],
            'guests' => $guests,
            'totals' => [
                'total' => $guests->count(),
                'attending' => $guests->where('rsvp_status', 'yes')->count(),
                'expected_headcount' => $guests->where('rsvp_status', 'yes')->sum('rsvp_party_size'),
                'checked_in' => $guests->where('checked_in', true)->count(),
            ],
        ]);
    }

    /**
     * Day-of door list formatted alphabetically with checkboxes for non-QR events.
     */
    public function doorList(Request $request, Event $event): Response
    {
        Gate::authorize('view', $event);

        $guests = $event->guests()
            ->orderBy('name')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'phone' => $g->phone,
                'party_size' => max(1, (int) ($g->rsvp_party_size ?: $g->max_party_size)),
                'rsvp_status' => $g->rsvp_status,
                'checked_in' => (bool) $g->checked_in_at,
                'letter' => mb_strtoupper(mb_substr($g->name, 0, 1)),
            ]);

        $ethiopianDate = EthiopianCalendar::fromGregorian($event->starts_at);

        return Inertia::render('Events/Exports/DoorList', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at?->format('l, F j, Y • g:i A'),
                'ethiopian_date' => $ethiopianDate,
                'venue_name' => $event->venue_name,
                'venue_address' => $event->venue_address,
                'host_names' => $event->host_names,
            ],
            'guests' => $guests,
            'totals' => [
                'total' => $guests->count(),
                'expected_headcount' => $guests->sum('party_size'),
            ],
        ]);
    }
}
