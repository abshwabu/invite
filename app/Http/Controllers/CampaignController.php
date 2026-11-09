<?php

namespace App\Http\Controllers;

use App\Contracts\SmsGateway;
use App\Jobs\SendDeliveryJob;
use App\Mail\InvitationEmail;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\Guest;
use App\Services\EntitlementService;
use App\Services\MessageTemplateService;
use App\Services\PhoneNormalizer;
use App\Services\QuotaService;
use App\Services\ShortLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(
        protected QuotaService $quotaService,
        protected EntitlementService $entitlementService,
        protected MessageTemplateService $templateService,
        protected ShortLinkService $shortLinkService
    ) {}

    protected function authorizeEvent(Request $request, Event $event): void
    {
        $userId = $request->user()->id;
        $isOwner = $event->user_id === $userId;
        $isMember = $event->memberUsers()->where('users.id', $userId)->exists();

        if (! $isOwner && ! $isMember) {
            abort(403, 'Unauthorized event action.');
        }
    }

    /**
     * Show the review and send screen for creating an invitation campaign.
     */
    public function create(Request $request, Event $event): Response
    {
        $this->authorizeEvent($request, $event);

        $user = $request->user();
        $tier = $this->entitlementService->getHighestTier($user);

        $guests = $event->guests;
        $totalGuests = $guests->count();
        $optedOutCount = $guests->where('opted_out', true)->count();
        $activeGuests = $guests->where('opted_out', false);

        $smsEligibleCount = $activeGuests->filter(fn ($g) => ! empty($g->phone))->count();
        $emailEligibleCount = $activeGuests->filter(fn ($g) => ! empty($g->email))->count();
        $bothEligibleCount = $activeGuests->filter(fn ($g) => ! empty($g->phone) || ! empty($g->email))->count();
        $bothCompleteCount = $activeGuests->filter(fn ($g) => ! empty($g->phone) && ! empty($g->email))->count();

        $availableCards = $this->quotaService->available($user);

        $smsWeight = $tier ? max(1, (int) $tier->sms_weight) : 1;
        $emailWeight = $tier ? max(1, (int) $tier->email_weight) : 1;

        $canSms = $this->entitlementService->can($user, 'sms_channel');
        $canEmail = true;

        $testSendsKey = 'test_sends:' . $user->id . ':' . now()->toDateString();
        $testSendsUsed = (int) Cache::get($testSendsKey, 0);

        return Inertia::render('Campaigns/Create', [
            'event' => $event,
            'stats' => [
                'total_guests' => $totalGuests,
                'opted_out_count' => $optedOutCount,
                'sms_eligible_count' => $smsEligibleCount,
                'email_eligible_count' => $emailEligibleCount,
                'both_eligible_count' => $bothEligibleCount,
                'both_complete_count' => $bothCompleteCount,
            ],
            'tier' => [
                'name' => $tier?->name ?? 'Free',
                'sms_weight' => $smsWeight,
                'email_weight' => $emailWeight,
                'can_sms' => $canSms,
                'can_email' => $canEmail,
            ],
            'availableCards' => $availableCards,
            'defaultTemplates' => [
                'en' => MessageTemplateService::DEFAULT_INVITATION_EN,
                'am' => MessageTemplateService::DEFAULT_INVITATION_AM,
            ],
            'testSends' => [
                'used' => $testSendsUsed,
                'remaining' => max(0, 3 - $testSendsUsed),
                'max' => 3,
            ],
            'currentUser' => [
                'phone' => $user->phone ?? '',
                'email' => $user->email ?? '',
            ],
        ]);
    }

    /**
     * Send a free test message to the host's phone or email (max 3/day).
     */
    public function testSend(Request $request, Event $event, SmsGateway $smsGateway)
    {
        $this->authorizeEvent($request, $event);

        $user = $request->user();
        $testSendsKey = 'test_sends:' . $user->id . ':' . now()->toDateString();
        $testSendsUsed = (int) Cache::get($testSendsKey, 0);

        if ($testSendsUsed >= 3) {
            return back()->withErrors([
                'test_send' => 'Daily test send limit reached (maximum 3 test sends per day).',
            ]);
        }

        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:sms,email'],
            'phone' => ['nullable', 'string', 'required_if:channel,sms'],
            'email' => ['nullable', 'email', 'required_if:channel,email'],
            'body' => ['required', 'string'],
            'subject' => ['nullable', 'string'],
        ]);

        // Sample guest data for test preview
        $sampleGuest = new Guest([
            'name' => $user->name ?: 'Sample Guest',
            'token' => 'sample-preview-token',
        ]);

        $previewUrl = route('public.invitation.show', ['token' => $sampleGuest->token]);

        $renderedBody = $this->templateService->render($validated['body'], [
            'guest_name' => $sampleGuest->name,
            'event_title' => $event->title,
            'date' => $event->starts_at ? $event->starts_at->format('Y-m-d') : date('Y-m-d'),
            'time' => $event->starts_at ? $event->starts_at->format('g:i A') : '6:00 PM',
            'venue' => $event->venue_name ?: 'Event Venue',
            'host_names' => $event->host_names ?: $user->name,
            'link' => $previewUrl,
        ]);

        if ($validated['channel'] === 'sms') {
            $phone = PhoneNormalizer::normalize($validated['phone']) ?: $validated['phone'];
            $result = $smsGateway->send($phone, $renderedBody);

            if (! $result->isSuccessful()) {
                return back()->withErrors([
                    'test_send' => 'Failed to send test SMS: ' . ($result->error ?: 'Unknown error'),
                ]);
            }
        } else {
            $subject = $validated['subject'] 
                ? $this->templateService->render($validated['subject'], ['event_title' => $event->title, 'host_names' => $event->host_names])
                : "{$event->title} - Test Invitation Preview";

            Mail::to($validated['email'])->send(
                new InvitationEmail($event, $sampleGuest, $previewUrl, $subject, $renderedBody)
            );
        }

        Cache::put($testSendsKey, $testSendsUsed + 1, now()->endOfDay());

        return back()->with('success', 'Test ' . strtoupper($validated['channel']) . ' sent successfully! (' . (2 - $testSendsUsed) . ' test sends remaining today)');
    }

    /**
     * Confirm and launch campaign dispatch.
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        $user = $request->user();
        $tier = $this->entitlementService->getHighestTier($user);

        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:sms,email,both'],
            'body' => ['required', 'string'],
            'subject' => ['nullable', 'string', 'required_if:channel,email,both'],
            'link_mode' => ['nullable', 'string', 'in:guest_invitation,event_page,custom_url,none'],
            'custom_url' => ['nullable', 'url', 'required_if:link_mode,custom_url'],
        ]);

        $channel = $validated['channel'];
        $smsWeight = $tier ? max(1, (int) $tier->sms_weight) : 1;
        $emailWeight = $tier ? max(1, (int) $tier->email_weight) : 1;

        // Filter eligible, non-opted-out guests
        $guestsQuery = $event->guests()->where('opted_out', false);

        if ($channel === 'sms') {
            $guestsQuery->whereNotNull('phone')->where('phone', '!=', '');
        } elseif ($channel === 'email') {
            $guestsQuery->whereNotNull('email')->where('email', '!=', '');
        } else {
            $guestsQuery->where(function ($q) {
                $q->where(fn ($sq) => $sq->whereNotNull('phone')->where('phone', '!=', ''))
                  ->orWhere(fn ($sq) => $sq->whereNotNull('email')->where('email', '!=', ''));
            });
        }

        $eligibleGuests = $guestsQuery->get();

        if ($eligibleGuests->isEmpty()) {
            return back()->withErrors([
                'channel' => 'No eligible guests found with contact info for the selected channel.',
            ]);
        }

        // Build list of planned deliveries and calculate total quota
        $plannedDeliveries = [];
        $totalQuotaNeeded = 0;

        foreach ($eligibleGuests as $guest) {
            if ($channel === 'sms') {
                $plannedDeliveries[] = ['guest_id' => $guest->id, 'channel' => 'sms', 'cost' => $smsWeight];
                $totalQuotaNeeded += $smsWeight;
            } elseif ($channel === 'email') {
                $plannedDeliveries[] = ['guest_id' => $guest->id, 'channel' => 'email', 'cost' => $emailWeight];
                $totalQuotaNeeded += $emailWeight;
            } else {
                // channel == 'both'
                if (! empty($guest->phone)) {
                    $plannedDeliveries[] = ['guest_id' => $guest->id, 'channel' => 'sms', 'cost' => $smsWeight];
                    $totalQuotaNeeded += $smsWeight;
                }
                if (! empty($guest->email)) {
                    $plannedDeliveries[] = ['guest_id' => $guest->id, 'channel' => 'email', 'cost' => $emailWeight];
                    $totalQuotaNeeded += $emailWeight;
                }
            }
        }

        // Atomically reserve quota
        $reservation = $this->quotaService->reserve($user, $totalQuotaNeeded, [
            'event_id' => $event->id,
            'kind' => 'invitation',
            'channel' => $channel,
        ]);

        if (! $reservation->isSuccessful()) {
            $available = $this->quotaService->available($user);
            return back()->withErrors([
                'quota' => "Insufficient card quota. You need {$totalQuotaNeeded} cards for this campaign, but only have {$available} available. Please upgrade your plan.",
                'upgrade_required' => true,
            ]);
        }

        // Create Campaign record
        $campaign = DB::transaction(function () use ($event, $user, $validated, $channel, $totalQuotaNeeded, $plannedDeliveries) {
            $campaign = Campaign::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'type' => 'invitation',
                'channel' => $channel,
                'subject' => $validated['subject'] ?? null,
                'body' => $validated['body'],
                'link_mode' => $validated['link_mode'] ?? 'guest_invitation',
                'custom_url' => $validated['custom_url'] ?? null,
                'audience' => ['status' => 'all_eligible'],
                'audience_count' => count($plannedDeliveries),
                'scheduled_at' => null,
                'status' => 'sending',
                'estimated_cards' => $totalQuotaNeeded,
            ]);

            foreach ($plannedDeliveries as $item) {
                $delivery = Delivery::create([
                    'campaign_id' => $campaign->id,
                    'guest_id' => $item['guest_id'],
                    'channel' => $item['channel'],
                    'status' => 'queued',
                    'quota_charged' => $item['cost'],
                ]);

                SendDeliveryJob::dispatch($delivery->id);
            }

            return $campaign;
        });

        return redirect()->route('events.deliveries', $event->id)
            ->with('success', "Campaign created! Dispatched {$campaign->audience_count} messages consuming {$totalQuotaNeeded} cards.");
    }

    /**
     * Show delivery log and status dashboard for an event.
     */
    public function deliveries(Request $request, Event $event): Response
    {
        $this->authorizeEvent($request, $event);

        $campaignId = $request->query('campaign_id');
        $channel = $request->query('channel');
        $status = $request->query('status');
        $search = $request->query('search');

        $query = Delivery::with(['guest', 'campaign'])
            ->whereHas('campaign', fn ($q) => $q->where('event_id', $event->id));

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        if ($channel && in_array($channel, ['sms', 'email'], true)) {
            $query->where('channel', $channel);
        }

        if ($status && in_array($status, ['queued', 'sending', 'sent', 'delivered', 'failed', 'bounced'], true)) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->whereHas('guest', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $deliveries = $query->latest('id')->paginate(25)->withQueryString();

        // Summary stats across event deliveries
        $allDeliveries = Delivery::whereHas('campaign', fn ($q) => $q->where('event_id', $event->id));
        $stats = [
            'total' => (clone $allDeliveries)->count(),
            'sent' => (clone $allDeliveries)->whereIn('status', ['sent', 'delivered'])->count(),
            'delivered' => (clone $allDeliveries)->where('status', 'delivered')->count(),
            'failed' => (clone $allDeliveries)->whereIn('status', ['failed', 'bounced'])->count(),
            'queued' => (clone $allDeliveries)->whereIn('status', ['queued', 'sending'])->count(),
        ];

        $campaigns = $event->campaigns()->latest()->get(['id', 'type', 'channel', 'status', 'created_at']);

        return Inertia::render('Events/Deliveries', [
            'event' => $event,
            'deliveries' => $deliveries,
            'stats' => $stats,
            'campaigns' => $campaigns,
            'filters' => [
                'campaign_id' => $campaignId,
                'channel' => $channel,
                'status' => $status,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Resend an individual delivery.
     */
    public function resendDelivery(Request $request, Event $event, Delivery $delivery): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        if ($delivery->campaign?->event_id !== $event->id) {
            abort(404, 'Delivery not found for this event.');
        }

        $user = $request->user();
        $tier = $this->entitlementService->getHighestTier($user);
        $cost = $delivery->channel === 'sms' 
            ? ($tier ? max(1, (int) $tier->sms_weight) : 1)
            : ($tier ? max(1, (int) $tier->email_weight) : 1);

        // If the delivery failed and was refunded, we must reserve quota again
        if ($delivery->quota_charged <= 0) {
            $reservation = $this->quotaService->reserve($user, $cost, [
                'event_id' => $event->id,
                'campaign_id' => $delivery->campaign_id,
                'guest_id' => $delivery->guest_id,
                'channel' => $delivery->channel,
                'kind' => 'invitation_resend',
            ]);

            if (! $reservation->isSuccessful()) {
                return back()->withErrors([
                    'quota' => 'Insufficient card quota to resend message. Please upgrade your plan.',
                ]);
            }

            $delivery->update(['quota_charged' => $cost]);
        }

        $delivery->update([
            'status' => 'queued',
            'error' => null,
            'attempts' => 0,
            'sent_at' => null,
        ]);

        SendDeliveryJob::dispatch($delivery->id);

        return back()->with('success', 'Message queued for resend.');
    }

    /**
     * Resend all failed deliveries in a campaign.
     */
    public function resendFailed(Request $request, Event $event, Campaign $campaign): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        if ($campaign->event_id !== $event->id) {
            abort(404, 'Campaign not found for this event.');
        }

        $user = $request->user();
        $tier = $this->entitlementService->getHighestTier($user);
        $smsWeight = $tier ? max(1, (int) $tier->sms_weight) : 1;
        $emailWeight = $tier ? max(1, (int) $tier->email_weight) : 1;

        $failedDeliveries = Delivery::where('campaign_id', $campaign->id)
            ->whereIn('status', ['failed', 'bounced'])
            ->get();

        if ($failedDeliveries->isEmpty()) {
            return back()->with('info', 'No failed deliveries found to resend.');
        }

        // Calculate quota needed for failed deliveries whose quota was refunded
        $totalQuotaNeeded = 0;
        foreach ($failedDeliveries as $del) {
            if ($del->quota_charged <= 0) {
                $totalQuotaNeeded += $del->channel === 'sms' ? $smsWeight : $emailWeight;
            }
        }

        if ($totalQuotaNeeded > 0) {
            $reservation = $this->quotaService->reserve($user, $totalQuotaNeeded, [
                'event_id' => $event->id,
                'campaign_id' => $campaign->id,
                'kind' => 'resend_failed',
            ]);

            if (! $reservation->isSuccessful()) {
                return back()->withErrors([
                    'quota' => "Insufficient card quota. Need {$totalQuotaNeeded} cards to resend failed deliveries. Please upgrade your plan.",
                ]);
            }
        }

        foreach ($failedDeliveries as $del) {
            $cost = $del->channel === 'sms' ? $smsWeight : $emailWeight;
            $del->update([
                'status' => 'queued',
                'error' => null,
                'attempts' => 0,
                'sent_at' => null,
                'quota_charged' => $del->quota_charged > 0 ? $del->quota_charged : $cost,
            ]);

            SendDeliveryJob::dispatch($del->id);
        }

        $campaign->update(['status' => 'sending']);

        return back()->with('success', "Queued {$failedDeliveries->count()} failed messages for resend.");
    }

    /**
     * Resend invitation to guests whose RSVP is still pending.
     */
    public function resendPending(Request $request, Event $event, Campaign $campaign): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        if ($campaign->event_id !== $event->id) {
            abort(404, 'Campaign not found for this event.');
        }

        $user = $request->user();
        $tier = $this->entitlementService->getHighestTier($user);
        $smsWeight = $tier ? max(1, (int) $tier->sms_weight) : 1;
        $emailWeight = $tier ? max(1, (int) $tier->email_weight) : 1;

        $pendingDeliveries = Delivery::where('campaign_id', $campaign->id)
            ->whereHas('guest', fn ($q) => $q->where('rsvp_status', 'pending')->where('opted_out', false))
            ->get();

        if ($pendingDeliveries->isEmpty()) {
            return back()->with('info', 'No pending RSVP guests found to resend.');
        }

        $totalQuotaNeeded = 0;
        foreach ($pendingDeliveries as $del) {
            $totalQuotaNeeded += $del->channel === 'sms' ? $smsWeight : $emailWeight;
        }

        $reservation = $this->quotaService->reserve($user, $totalQuotaNeeded, [
            'event_id' => $event->id,
            'campaign_id' => $campaign->id,
            'kind' => 'resend_pending',
        ]);

        if (! $reservation->isSuccessful()) {
            return back()->withErrors([
                'quota' => "Insufficient card quota. Need {$totalQuotaNeeded} cards to resend to pending guests. Please upgrade your plan.",
            ]);
        }

        foreach ($pendingDeliveries as $del) {
            $cost = $del->channel === 'sms' ? $smsWeight : $emailWeight;
            $del->update([
                'status' => 'queued',
                'error' => null,
                'attempts' => 0,
                'sent_at' => null,
                'quota_charged' => $cost,
            ]);

            SendDeliveryJob::dispatch($del->id);
        }

        $campaign->update(['status' => 'sending']);

        return back()->with('success', "Queued {$pendingDeliveries->count()} messages to pending guests.");
    }
}
