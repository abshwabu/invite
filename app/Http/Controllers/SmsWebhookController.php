<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Services\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SmsWebhookController extends Controller
{
    public function __construct(
        protected QuotaService $quotaService
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        Log::info("SMS Webhook received for provider [{$provider}]", [
            'payload' => $request->all(),
            'query' => $request->query(),
        ]);

        $messageId = $request->input('message_id')
            ?? $request->input('id')
            ?? $request->input('provider_id')
            ?? $request->input('response.message_id')
            ?? $request->query('message_id');

        $deliveryId = $request->input('delivery_id') ?? $request->query('delivery_id');

        $delivery = null;
        if ($messageId) {
            $delivery = Delivery::where('provider_message_id', $messageId)->first();
        }

        if (! $delivery && $deliveryId) {
            $delivery = Delivery::find($deliveryId);
        }

        if (! $delivery) {
            Log::warning("SmsWebhookController: Delivery not found for message_id [{$messageId}] or delivery_id [{$deliveryId}]");
            return response()->json(['status' => 'not_found', 'message_id' => $messageId], 200);
        }

        $rawStatus = strtolower((string) (
            $request->input('status')
            ?? $request->input('delivery_status')
            ?? $request->input('event')
            ?? 'delivered'
        ));

        if (str_contains($rawStatus, 'fail') || str_contains($rawStatus, 'undeliver') || str_contains($rawStatus, 'reject') || str_contains($rawStatus, 'bounce')) {
            $status = str_contains($rawStatus, 'bounce') ? 'bounced' : 'failed';
            $errorMsg = $request->input('error') 
                ?? $request->input('reason') 
                ?? $request->input('message') 
                ?? "Provider reported delivery {$status}";

            // Only refund if we haven't already marked failed / refunded
            if ($delivery->status !== 'failed' && $delivery->status !== 'bounced' && $delivery->quota_charged > 0) {
                $campaign = $delivery->campaign;
                $event = $campaign?->event;
                $userId = $event?->user_id ?? $campaign?->user_id;

                $this->quotaService->refund([
                    'user_id' => $userId,
                    'event_id' => $event?->id,
                    'campaign_id' => $campaign?->id,
                    'guest_id' => $delivery->guest_id,
                    'channel' => $delivery->channel,
                ], $delivery->quota_charged);

                $delivery->update([
                    'status' => $status,
                    'error' => $errorMsg,
                    'quota_charged' => 0,
                ]);
            } else {
                $delivery->update([
                    'status' => $status,
                    'error' => $errorMsg,
                ]);
            }
        } elseif (str_contains($rawStatus, 'deliver') || str_contains($rawStatus, 'success')) {
            $delivery->update([
                'status' => 'delivered',
                'error' => null,
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'delivery_id' => $delivery->id,
            'delivery_status' => $delivery->status,
        ]);
    }
}
