<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

class GeezSmsGateway implements SmsGateway
{
    public function __construct(
        protected ?string $token = null
    ) {
        $this->token = $token ?? (string) config('invite.sms.geezsms.token', env('GEEZSMS_TOKEN', ''));
    }

    public function send(string $to, string $message, array $options = []): SmsResult
    {
        $messageId = 'geez_' . bin2hex(random_bytes(8));

        Log::info("GeezSmsGateway (stubbed): To: {$to}, Message: {$message}, ID: {$messageId}");

        return SmsResult::success($messageId, [
            'provider' => 'geezsms',
            'to' => $to,
            'stub' => true,
        ]);
    }
}
