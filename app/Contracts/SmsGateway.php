<?php

namespace App\Contracts;

use App\Services\Sms\SmsResult;

interface SmsGateway
{
    /**
     * Send an SMS message to a phone number.
     *
     * @param string $to Normalized phone number
     * @param string $message Text content
     * @param array $options Additional options (sender_id, callback_url, etc.)
     * @return SmsResult
     */
    public function send(string $to, string $message, array $options = []): SmsResult;
}
