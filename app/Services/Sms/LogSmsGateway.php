<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

class LogSmsGateway implements SmsGateway
{
    /**
     * @var array<int, array{to: string, message: string, sent_at: string}>
     */
    public static array $sentMessages = [];

    public function send(string $to, string $message): bool
    {
        Log::info("Sending SMS to [{$to}]: {$message}");

        self::$sentMessages[] = [
            'to' => $to,
            'message' => $message,
            'sent_at' => now()->toIso8601String(),
        ];

        return true;
    }

    public static function clearSent(): void
    {
        self::$sentMessages = [];
    }

    public static function lastMessageFor(string $to): ?string
    {
        for ($i = count(self::$sentMessages) - 1; $i >= 0; $i--) {
            if (self::$sentMessages[$i]['to'] === $to) {
                return self::$sentMessages[$i]['message'];
            }
        }

        return null;
    }
}
