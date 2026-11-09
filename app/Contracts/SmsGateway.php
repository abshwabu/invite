<?php

namespace App\Contracts;

interface SmsGateway
{
    /**
     * Send an SMS message to a phone number.
     *
     * @param string $to Normalized phone number
     * @param string $message Text content
     * @return bool
     */
    public function send(string $to, string $message): bool;
}
