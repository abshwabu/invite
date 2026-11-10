<?php

namespace App\Http\Controllers;

use App\Contracts\SmsGateway;
use App\Jobs\SendDeliveryJob;
use App\Mail\InvitationEmail;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\User;
use App\Services\AudienceService;
use App\Services\ContentSafeguardService;
use App\Services\EntitlementService;
use App\Services\MessageTemplateService;
use App\Services\PhoneNormalizer;
use App\Services\QuotaService;
use App\Services\ShortLinkService;
use App\Services\UrlSafetyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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
        protected ShortLinkService $shortLinkService,
        protected AudienceService $audienceService,
        protected UrlSafetyService $urlSafetyService,
        protected ContentSafeguardService $safeguardService
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

    protected function quotaInsufficientError(User $user, int $needed): array
    {
        $hasPendingPayment = Payment::where('user_id', $user->id)
            ->whereIn('status', ['submitted', 'needs_info'])
            ->exists();

        if ($hasPendingPayment) {
            return [
                'quota' => 'Your payment is being verified. You can send your cards after we verify it, usually within 24 hours.',
                'payment_pending' => true,
            ];
        }

        $available = $this->quotaService->available($user);
        return [
            'quota' => "Insufficient card quota. You need {$needed} cards, but only have {$available} available. Please upgrade your plan.",
            'upgrade_required' => true,
        ];
    }

    /**
     * Show campaign history list for an event.
     */
    public function index(Request $request, Event $event): Response
    {
        $this->authorizeEvent($request, $event);

        $campaigns = $event->campaigns()
            ->withCount([
                'deliveries as total_deliveries_count',
                'deliveries as sent_count' => fn ($q) => $q->whereIn('status', ['sent', 'delivered']),
                'deliveries as delivered_count' => fn ($q) => $q->where('status', 'delivered'),
                'deliveries as failed_count' => fn ($q) => $q->whereIn('status', ['failed', 'bounced']),
            ])
            ->withSum('shortLinks as link_clicks', 'clicks')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $canCustomMessages = $this->entitlementService->can($request->user(), 'custom_messages');

        return Inertia::render('Campaigns/Index', [
            'event' => $event,
            'campaigns' => $campaigns,
            'canCustomMessages' => $canCustomMessages,
        ]);
    }

    /**
     * Show the review and compose screen for invitations or custom messages.
     */
    public function create(Request $request, Event $event): Response|RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        $user = $request->user();
        $isCustom = $request->query('type') === 'custom' || $request->routeIs('events.messages.create');

        // Check entitlement for custom messaging
        if ($isCustom && ! $this->entitlementService->can($user, 'custom_messages')) {
            return redirect()->route('plan.index')->withErrors([
                'feature' => 'Custom messages require an upgraded tier. Please upgrade your plan to send custom messages.',
            ]);
        }

        $tier = $this->entitlementService->getHighestTier($user);
        $canSms = $this->entitlementService->can($user, 'sms_channel');
        $canEmail = true;
        $canCustomLinks = $this->entitlementService->can($user, 'custom_links');

        $guests = $event->guests;
        $activeGuests = $guests->where('opted_out', false);

        // Precompute audience counts
        $audienceCounts = [
            'all' => $guests->count(),
            'rsvp_pending' => $guests->where('rsvp_status', 'pending')->count(),
            'rsvp_yes' => $guests->where('rsvp_status', 'yes')->count(),
            'rsvp_no' => $guests->where('rsvp_status', 'no')->count(),
            'rsvp_maybe' => $guests->where('rsvp_status', 'maybe')->count(),
            'checked_in' => $guests->whereNotNull('checked_in_at')->count(),
            'not_checked_in' => $guests->whereNull('checked_in_at')->count(),
            'not_viewed' => $guests->whereNull('viewed_at')->count(),
            'failed_deliveries' => Delivery::whereHas('campaign', fn ($q) => $q->where('event_id', $event->id))
                ->whereIn('status', ['failed', 'bounced'])
                ->distinct('guest_id')
                ->count('guest_id'),
        ];

        $availableCards = $this->quotaService->available($user);

        $smsWeight = $tier ? max(1, (int) $tier->sms_weight) : 1;
        $emailWeight = $tier ? max(1, (int) $tier->email_weight) : 1;

        $testSendsKey = 'test_sends:' . $user->id . ':' . now()->toDateString();
        $testSendsUsed = (int) Cache::get($testSendsKey, 0);

        $isNewAccount = $this->safeguardService->isNewAccountRequiringReview($user);

        return Inertia::render('Campaigns/Create', [
            'event' => $event,
            'isCustom' => $isCustom,
            'audienceCounts' => $audienceCounts,
            'guests' => $guests->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'phone' => $g->phone,
                'email' => $g->email,
                'rsvp_status' => $g->rsvp_status,
                'opted_out' => (bool) $g->opted_out,
                'viewed_at' => $g->viewed_at,
                'checked_in_at' => $g->checked_in_at,
            ]),
            'stats' => [
                'total_guests' => $guests->count(),
                'opted_out_count' => $guests->where('opted_out', true)->count(),
                'sms_eligible_count' => $activeGuests->filter(fn ($g) => ! empty($g->phone))->count(),
                'email_eligible_count' => $activeGuests->filter(fn ($g) => ! empty($g->email))->count(),
                'both_eligible_count' => $activeGuests->filter(fn ($g) => ! empty($g->phone) || ! empty($g->email))->count(),
                'both_complete_count' => $activeGuests->filter(fn ($g) => ! empty($g->phone) && ! empty($g->email))->count(),
            ],
            'tier' => [
                'name' => $tier?->name ?? 'Free',
                'sms_weight' => $smsWeight,
                'email_weight' => $emailWeight,
                'can_sms' => $canSms,
                'can_email' => $canEmail,
                'can_custom_messages' => $this->entitlementService->can($user, 'custom_messages'),
                'can_custom_links' => $canCustomLinks,
            ],
            'availableCards' => $availableCards,
            'defaultTemplates' => [
                'en' => $isCustom
                    ? 'Dear {guest_name}, this is an update regarding {event_title}: [your message here]. View details: {link}'
                    : MessageTemplateService::DEFAULT_INVITATION_EN,
                'am' => $isCustom
                    ? 'ውድ {guest_name}፡ ይህ ስለ {event_title} የተላከ መልዕክት ነው፡ [መልዕክትዎን እዚህ ያስገቡ]። ዝርዝሩን ይመልከቱ፡ {link}'
                    : MessageTemplateService::DEFAULT_INVITATION_AM,
            ],
            'testSends' => [
                'used' => $testSendsUsed,
                'remaining' => max(0, 3 - $testSendsUsed),
                'max' => 3,
            ],
            'currentUser' => [
                'phone' => $user->phone ?? '',
                'email' => $user->email ?? '',
                'is_new_account' => $isNewAccount,
            ],
        ]);
    }

    /**
     * Dynamic audience resolution statistics endpoint.
     */
    public function audienceStats(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($request, $event);

        $audienceType = $request->query('audience', 'all');
        $channel = $request->query('channel', 'sms');
        $guestIds = (array) $request->query('guest_ids', []);

        $resolved = $this->audienceService->resolve($event, $audienceType, $guestIds, $channel);

        return response()->json($resolved['stats']);
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
                : "{$event->title} - Test Preview";

            Mail::to($validated['email'])->send(
                new InvitationEmail($event, $sampleGuest, $previewUrl, $subject, $renderedBody)
            );
        }

        Cache::put($testSendsKey, $testSendsUsed + 1, now()->endOfDay());

        return back()->with('success', 'Test ' . strtoupper($validated['channel']) . ' sent successfully! (' . (2 - $testSendsUsed) . ' test sends remaining today)');
    }

    /**
     * Store and launch or schedule a campaign.
     */
    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        $user = $request->user();

        // 0. Safeguard: suspended event or user cannot send campaigns
        if ($event->status === 'suspended' || $user->is_suspended) {
            return back()->withErrors([
                'event' => 'Cannot create or dispatch messages for suspended events or accounts.',
            ]);
        }

        $type = $request->input('type', 'invitation');
        $isCustom = $type === 'custom';
        $isThankYou = $type === 'thank_you';

        // 1. Feature gating
        if ($isCustom && ! $this->entitlementService->can($user, 'custom_messages')) {
            return back()->withErrors([
                'custom_messages' => 'Your subscription tier does not allow sending custom messages.',
            ]);
        }

        if ($isThankYou && ! $this->entitlementService->can($user, 'thank_you')) {
            return back()->withErrors([
                'thank_you' => 'Your subscription tier does not allow sending thank-you cards. Please upgrade your plan.',
            ]);
        }

        $linkMode = $request->input('link_mode', $isThankYou ? 'thank_you' : 'guest_invitation');
        if ($linkMode === 'custom_url' && ! $this->entitlementService->can($user, 'custom_links')) {
            return back()->withErrors([
                'custom_links' => 'Custom external URLs require the custom_links tier feature. Please upgrade your plan.',
            ]);
        }

        // 2. Validation
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:invitation,custom,thank_you'],
            'channel' => ['required', 'string', 'in:sms,email,both'],
            'body' => ['required', 'string'],
            'subject' => ['nullable', 'string', 'required_if:channel,email,both'],
            'link_mode' => ['nullable', 'string', 'in:guest_invitation,event_page,custom_url,thank_you,none'],
            'custom_url' => ['nullable', 'string', 'required_if:link_mode,custom_url'],
            'audience_type' => ['nullable', 'string', 'in:all,rsvp_pending,rsvp_yes,rsvp_no,rsvp_maybe,checked_in,not_checked_in,not_viewed,failed_deliveries,manual'],
            'guest_ids' => ['nullable', 'array'],
            'guest_ids.*' => ['integer'],
            'scheduled_at' => ['nullable', 'date'],
            'schedule_hours_after_end' => ['nullable', 'integer', 'min:1'],
            'thankyou_note' => ['nullable', 'string', 'max:2000'],
            'thankyou_photo_url' => ['nullable', 'string', 'max:500'],
        ]);

        // Save optional thank-you note and photo link on the event
        if ($request->has('thankyou_note') || $request->has('thankyou_photo_url')) {
            $event->update([
                'thankyou_note' => $request->input('thankyou_note', $event->thankyou_note),
                'thankyou_photo_url' => $request->input('thankyou_photo_url', $event->thankyou_photo_url),
            ]);
        }

        $type = $validated['type'] ?? 'invitation';
        $channel = $validated['channel'];
        $audienceType = $validated['audience_type'] ?? ($isThankYou ? $this->audienceService->defaultThankYouAudience($event) : 'all');
        $guestIds = $validated['guest_ids'] ?? [];
        $body = $validated['body'];
        $subject = $validated['subject'] ?? null;
        $linkMode = $validated['link_mode'] ?? ($isThankYou ? 'thank_you' : 'guest_invitation');
        $customUrl = $validated['custom_url'] ?? null;

        // 3. Custom URL Safety check
        $isFlaggedUrl = false;
        if ($linkMode === 'custom_url') {
            $safety = $this->urlSafetyService->validate($customUrl);
            if (! $safety['valid']) {
                return back()->withErrors([
                    'custom_url' => $safety['error'] ?? 'The provided URL failed security verification.',
                ]);
            }
            $isFlaggedUrl = $safety['flagged'];
        }

        // 4. Prohibited Content Keyword filter
        $textToCheck = $body . ' ' . ($subject ?? '');
        $prohibited = $this->safeguardService->checkProhibitedContent($textToCheck);
        if ($prohibited) {
            return back()->withErrors([
                'body' => "Your message contains prohibited phrasing or unauthorized keywords ('{$prohibited}').",
            ]);
        }

        // 5. Host name transparency in SMS
        if ($channel === 'sms' || $channel === 'both') {
            $body = $this->safeguardService->ensureHostNameInSms($body, $event->host_names ?: $user->name);
        }

        // 6. Resolve Audience
        $resolved = $this->audienceService->resolve($event, $audienceType, $guestIds, $channel);
        $eligibleGuests = $resolved['eligible_guests'];

        if ($eligibleGuests->isEmpty()) {
            return back()->withErrors([
                'channel' => 'No eligible guests found with contact info for the selected audience and channel.',
            ]);
        }

        $tier = $this->entitlementService->getHighestTier($user);
        $consumesQuota = ($type !== 'thank_you') || ($tier && $tier->thank_you_consumes_quota);
        $smsWeight = ($tier && $consumesQuota) ? max(1, (int) $tier->sms_weight) : ($consumesQuota ? 1 : 0);
        $emailWeight = ($tier && $consumesQuota) ? max(1, (int) $tier->email_weight) : ($consumesQuota ? 1 : 0);

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

        // 7. Check Daily Send Cap for new accounts (only if consumes quota)
        if ($totalQuotaNeeded > 0) {
            $capError = $this->safeguardService->checkDailySendCap($user, $totalQuotaNeeded);
            if ($capError) {
                return back()->withErrors(['quota' => $capError]);
            }
        }

        // 8. Handle Scheduling
        $scheduledAt = ! empty($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null;
        if (! $scheduledAt && ! empty($validated['schedule_hours_after_end'])) {
            $hours = (int) $validated['schedule_hours_after_end'];
            $base = $event->ends_at ?: $event->starts_at->addHours(4);
            $calculated = $base->copy()->addHours($hours);
            if ($calculated->isFuture()) {
                $scheduledAt = $calculated;
            }
        }

        $isScheduled = $scheduledAt && $scheduledAt->isFuture();

        if ($isScheduled) {
            // Scheduled send: Quota is NOT reserved now, but warned if insufficient
            $available = $this->quotaService->available($user);

            $campaign = Campaign::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'type' => $validated['type'] ?? 'invitation',
                'channel' => $channel,
                'subject' => $subject,
                'body' => $body,
                'link_mode' => $linkMode,
                'custom_url' => $customUrl,
                'audience' => ['type' => $audienceType, 'guest_ids' => $guestIds],
                'audience_count' => count($plannedDeliveries),
                'scheduled_at' => $scheduledAt,
                'status' => 'scheduled',
                'estimated_cards' => $totalQuotaNeeded,
            ]);

            $msg = "Campaign scheduled for {$scheduledAt->toDateTimeString()}.";
            if ($available < $totalQuotaNeeded) {
                $msg .= " Note: You currently have {$available} cards, but will need {$totalQuotaNeeded} cards at send time.";
            }

            return redirect()->route('events.campaigns.index', $event->id)->with('success', $msg);
        }

        // 9. Handle New Account Review for Custom URLs
        $requiresReview = ($linkMode === 'custom_url') && $this->safeguardService->isNewAccountRequiringReview($user);

        if ($requiresReview) {
            $campaign = Campaign::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'type' => $validated['type'] ?? 'custom',
                'channel' => $channel,
                'subject' => $subject,
                'body' => $body,
                'link_mode' => $linkMode,
                'custom_url' => $customUrl,
                'audience' => ['type' => $audienceType, 'guest_ids' => $guestIds],
                'audience_count' => count($plannedDeliveries),
                'scheduled_at' => null,
                'status' => 'pending_review',
                'estimated_cards' => $totalQuotaNeeded,
            ]);

            $user->notify(new \App\Notifications\HostAlertNotification(
                type: 'campaign_pending_review',
                title: __('Campaign Pending Review'),
                message: __("Your message with custom external link ':url' is pending review before delivery.", ['url' => $customUrl]),
                meta: ['campaign_id' => $campaign->id, 'event_id' => $event->id],
                actionUrl: route('events.campaigns.index', $event->id)
            ));

            return redirect()->route('events.campaigns.index', $event->id)->with(
                'info',
                'Your message with a custom external link has been submitted for administrative review before delivery.'
            );
        }

        // 10. Send Now: Reserve Quota
        $reservation = $this->quotaService->reserve($user, $totalQuotaNeeded, [
            'event_id' => $event->id,
            'kind' => $validated['type'] ?? 'invitation',
            'channel' => $channel,
        ]);

        if (! $reservation->isSuccessful()) {
            return back()->withErrors($this->quotaInsufficientError($user, $totalQuotaNeeded));
        }

        // Create Campaign and dispatch jobs
        $campaign = DB::transaction(function () use ($event, $user, $validated, $channel, $subject, $body, $linkMode, $customUrl, $audienceType, $guestIds, $totalQuotaNeeded, $plannedDeliveries) {
            $campaign = Campaign::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'type' => $validated['type'] ?? 'invitation',
                'channel' => $channel,
                'subject' => $subject,
                'body' => $body,
                'link_mode' => $linkMode,
                'custom_url' => $customUrl,
                'audience' => ['type' => $audienceType, 'guest_ids' => $guestIds],
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
            ->with('success', "Dispatched {$campaign->audience_count} messages consuming {$totalQuotaNeeded} cards.");
    }

    /**
     * Cancel a scheduled campaign before it starts.
     */
    public function cancel(Request $request, Event $event, Campaign $campaign): RedirectResponse
    {
        $this->authorizeEvent($request, $event);

        if ($campaign->event_id !== $event->id) {
            abort(404, 'Campaign not found for this event.');
        }

        if ($campaign->status !== 'scheduled') {
            return back()->withErrors(['cancel' => 'Only scheduled campaigns can be cancelled.']);
        }

        $campaign->update(['status' => 'cancelled']);

        return back()->with('success', 'Scheduled campaign has been cancelled.');
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

        if ($delivery->quota_charged <= 0) {
            $reservation = $this->quotaService->reserve($user, $cost, [
                'event_id' => $event->id,
                'campaign_id' => $delivery->campaign_id,
                'guest_id' => $delivery->guest_id,
                'channel' => $delivery->channel,
                'kind' => 'invitation_resend',
            ]);

            if (! $reservation->isSuccessful()) {
                return back()->withErrors($this->quotaInsufficientError($user, $cost));
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
                return back()->withErrors($this->quotaInsufficientError($user, $totalQuotaNeeded));
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
            return back()->withErrors($this->quotaInsufficientError($user, $totalQuotaNeeded));
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
