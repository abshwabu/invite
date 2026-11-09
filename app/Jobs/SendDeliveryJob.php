<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use App\Mail\InvitationEmail;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Services\MessageTemplateService;
use App\Services\QuotaService;
use App\Services\ShortLinkService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 90];

    public function __construct(
        public int $deliveryId
    ) {}

    public function handle(
        SmsGateway $smsGateway,
        ShortLinkService $shortLinkService,
        MessageTemplateService $templateService,
        QuotaService $quotaService
    ): void {
        $delivery = Delivery::with(['guest', 'campaign.event.user'])->find($this->deliveryId);

        if (! $delivery) {
            Log::warning("SendDeliveryJob: Delivery #{$this->deliveryId} not found.");
            return;
        }

        // Idempotency: if already sent or delivered, skip
        if (in_array($delivery->status, ['sent', 'delivered'], true)) {
            return;
        }

        $campaign = $delivery->campaign;
        $guest = $delivery->guest;
        $event = $campaign?->event;

        if (! $guest || ! $event) {
            $delivery->update([
                'status' => 'failed',
                'error' => 'Missing associated guest or event record.',
            ]);
            $this->refundDeliveryQuota($quotaService, $delivery, $campaign, $event);
            return;
        }

        // Automatic skip & refund for opted-out guests
        if ($guest->opted_out) {
            $delivery->update([
                'status' => 'failed',
                'error' => 'Guest has opted out of invitations.',
            ]);
            $this->refundDeliveryQuota($quotaService, $delivery, $campaign, $event);
            return;
        }

        // Track attempts
        $delivery->increment('attempts');

        // Resolve link based on campaign link_mode
        $linkMode = $campaign->link_mode ?? 'guest_invitation';
        $shortUrl = '';

        if ($linkMode === 'event_page') {
            $targetUrl = url('/e/' . $event->public_slug);
            $shortLink = $shortLinkService->create($targetUrl, null, 8, $campaign->id, false);
            $shortUrl = url('/s/' . $shortLink->code);
        } elseif ($linkMode === 'custom_url' && ! empty($campaign->custom_url)) {
            $targetUrl = $campaign->custom_url;
            $safety = app(\App\Services\UrlSafetyService::class)->validate($targetUrl);
            $shortLink = $shortLinkService->create($targetUrl, null, 8, $campaign->id, $safety['flagged'] ?? false);
            $shortUrl = url('/s/' . $shortLink->code);
        } elseif ($linkMode === 'none') {
            $shortUrl = '';
        } elseif ($linkMode === 'thank_you' || ($campaign && $campaign->type === 'thank_you')) {
            $targetUrl = url('/t/' . $guest->token);
            $shortLink = $shortLinkService->create($targetUrl, null, 8, $campaign->id, false);
            $shortUrl = url('/s/' . $shortLink->code);
        } else {
            // default: guest_invitation
            $targetUrl = route('public.invitation.show', ['token' => $guest->token]);
            $shortLink = $shortLinkService->create($targetUrl, null, 8, $campaign->id, false);
            $shortUrl = url('/s/' . $shortLink->code);
        }

        // Render message
        $templateData = [
            'guest_name' => $guest->name,
            'event_title' => $event->title,
            'date' => $event->starts_at ? $event->starts_at->format('Y-m-d') : 'TBA',
            'time' => $event->starts_at ? $event->starts_at->format('g:i A') : '',
            'venue' => $event->venue_name ?? '',
            'host_names' => $event->host_names ?? ($event->user?->name ?? ''),
            'link' => $shortUrl,
        ];

        $renderedMessage = $templateService->render($campaign->body, $templateData);

        // If link exists and was not in template body, append it
        if (! empty($shortUrl) && ! str_contains($campaign->body, '{link}') && ! str_contains($campaign->body, '{{link}}')) {
            $renderedMessage = rtrim($renderedMessage) . "\n\n" . $shortUrl;
        }

        if ($delivery->channel === 'sms') {
            if (empty($guest->phone)) {
                $delivery->update([
                    'status' => 'failed',
                    'error' => 'Guest does not have a phone number.',
                ]);
                $this->refundDeliveryQuota($quotaService, $delivery, $campaign, $event);
                $this->notifyHostDeliveryFailure($delivery, $campaign, $event);
                return;
            }

            $result = $smsGateway->send($guest->phone, $renderedMessage);

            if ($result->isSuccessful()) {
                $delivery->update([
                    'status' => 'sent',
                    'provider_message_id' => $result->messageId,
                    'sent_at' => now(),
                    'error' => null,
                ]);
                $this->checkCampaignStatus($campaign);
            } else {
                throw new Exception($result->error ?: 'SMS gateway failed to deliver message.');
            }
        } elseif ($delivery->channel === 'email') {
            if (empty($guest->email)) {
                $delivery->update([
                    'status' => 'failed',
                    'error' => 'Guest does not have an email address.',
                ]);
                $this->refundDeliveryQuota($quotaService, $delivery, $campaign, $event);
                $this->notifyHostDeliveryFailure($delivery, $campaign, $event);
                return;
            }

            $subject = $campaign->subject 
                ? $templateService->render($campaign->subject, $templateData)
                : null;

            Mail::to($guest->email)->send(
                new InvitationEmail($event, $guest, $shortUrl, $subject, $renderedMessage)
            );

            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error' => null,
            ]);
            $this->checkCampaignStatus($campaign);
        }
    }

    /**
     * Handle a permanent failure after all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $delivery = Delivery::with(['guest', 'campaign.event.user'])->find($this->deliveryId);
        if (! $delivery) {
            return;
        }

        $delivery->update([
            'status' => 'failed',
            'error' => $exception?->getMessage() ?? 'Delivery failed after maximum retries.',
        ]);

        $quotaService = app(QuotaService::class);
        $this->refundDeliveryQuota($quotaService, $delivery, $delivery->campaign, $delivery->campaign?->event);
        $this->notifyHostDeliveryFailure($delivery, $delivery->campaign, $delivery->campaign?->event);
        $this->checkCampaignStatus($delivery->campaign);
    }

    protected function notifyHostDeliveryFailure(Delivery $delivery, ?Campaign $campaign, $event): void
    {
        $user = $event?->user ?? $campaign?->user;
        if (! $user) {
            return;
        }

        $user->notify(new \App\Notifications\HostAlertNotification(
            type: 'delivery_failure',
            title: __('Delivery Failure'),
            message: __("Delivery to :name via :channel failed: :error", [
                'name' => $delivery->guest?->name ?? 'Guest',
                'channel' => strtoupper($delivery->channel),
                'error' => $delivery->error ?? 'Delivery failed.',
            ]),
            meta: [
                'delivery_id' => $delivery->id,
                'campaign_id' => $campaign?->id,
                'event_id' => $event?->id,
                'guest_id' => $delivery->guest_id,
            ],
            actionUrl: $event ? route('events.deliveries', ['event' => $event->id]) : null
        ));
    }

    protected function refundDeliveryQuota(QuotaService $quotaService, Delivery $delivery, ?Campaign $campaign, $event): void
    {
        if ($delivery->quota_charged <= 0) {
            return;
        }

        $userId = $event?->user_id ?? $campaign?->user_id;

        $quotaService->refund([
            'user_id' => $userId,
            'event_id' => $event?->id,
            'campaign_id' => $campaign?->id,
            'guest_id' => $delivery->guest_id,
            'channel' => $delivery->channel,
        ], $delivery->quota_charged);

        // Set quota_charged to 0 so we never refund twice
        $delivery->update(['quota_charged' => 0]);
    }

    protected function checkCampaignStatus(?Campaign $campaign): void
    {
        if (! $campaign) {
            return;
        }

        $hasPending = Delivery::where('campaign_id', $campaign->id)
            ->whereIn('status', ['queued', 'sending'])
            ->exists();

        if (! $hasPending) {
            $hasSuccess = Delivery::where('campaign_id', $campaign->id)
                ->whereIn('status', ['sent', 'delivered'])
                ->exists();

            $campaign->update([
                'status' => $hasSuccess ? 'sent' : 'failed',
            ]);
        }
    }
}
