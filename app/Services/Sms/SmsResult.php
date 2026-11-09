<?php

namespace App\Services\Sms;

class SmsResult
{
    public function __construct(
        public bool $success,
        public ?string $messageId = null,
        public ?string $error = null,
        public array $raw = []
    ) {}

    public static function success(string $messageId, array $raw = []): self
    {
        return new self(true, $messageId, null, $raw);
    }

    public static function failure(string $error, array $raw = []): self
    {
        return new self(false, null, $error, $raw);
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }
}
