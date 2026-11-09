<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AfroMessageGateway implements SmsGateway
{
    protected string $token;
    protected ?string $senderId;
    protected ?string $callbackUrl;
    protected string $endpoint;

    public function __construct(
        ?string $token = null,
        ?string $senderId = null,
        ?string $callbackUrl = null,
        string $endpoint = 'https://api.afromessage.com/api/send'
    ) {
        $this->token = $token ?? (string) config('invite.sms.afromessage.token', env('AFROMESSAGE_TOKEN', ''));
        $this->senderId = $senderId ?? config('invite.sms.afromessage.sender_id', config('invite.sms.sender_id', 'INVITE'));
        $this->callbackUrl = $callbackUrl ?? config('invite.sms.afromessage.callback_url');
        $this->endpoint = $endpoint;
    }

    public function send(string $to, string $message, array $options = []): SmsResult
    {
        if (empty($this->token)) {
            Log::warning("AfroMessageGateway: Missing API token, cannot send SMS to {$to}");
            return SmsResult::failure('AfroMessage API token is not configured.');
        }

        $payload = [
            'to' => $to,
            'message' => $message,
        ];

        $from = $options['sender_id'] ?? $this->senderId;
        if (! empty($from)) {
            $payload['from'] = $from;
        }

        $callback = $options['callback_url'] ?? $this->callbackUrl;
        if (! empty($callback)) {
            $payload['callback'] = $callback;
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->acceptJson()
                ->post($this->endpoint, $payload);

            $data = $response->json() ?? [];

            if ($response->successful()) {
                $ack = $data['acknowledge'] ?? ($data['status'] ?? null);
                if ($ack === 'success' || (isset($data['response']['status']) && $data['response']['status'] === 'success')) {
                    $messageId = $data['response']['message_id'] 
                        ?? $data['message_id'] 
                        ?? $data['response']['id'] 
                        ?? ('afro_' . bin2hex(random_bytes(6)));

                    return SmsResult::success((string) $messageId, $data);
                }

                $errorMessage = $data['response']['errors'][0] 
                    ?? $data['message'] 
                    ?? $data['error'] 
                    ?? 'AfroMessage API returned an unsuccessful response.';

                Log::error("AfroMessageGateway error: {$errorMessage}", ['payload' => $payload, 'response' => $data]);
                return SmsResult::failure($errorMessage, $data);
            }

            $errorMessage = $data['response']['errors'][0] 
                ?? $data['message'] 
                ?? $data['error'] 
                ?? "HTTP error {$response->status()}";
            Log::error("AfroMessageGateway HTTP error: {$errorMessage}", ['status' => $response->status(), 'response' => $data]);
            return SmsResult::failure($errorMessage, $data);
        } catch (Throwable $e) {
            Log::error("AfroMessageGateway exception: " . $e->getMessage(), ['to' => $to]);
            return SmsResult::failure($e->getMessage());
        }
    }
}
