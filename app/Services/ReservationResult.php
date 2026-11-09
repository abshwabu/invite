<?php

namespace App\Services;

class ReservationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $requested,
        public readonly int $reserved,
        public readonly array $ledgerIds = [],
        public readonly ?string $message = null
    ) {}

    public static function success(int $requested, int $reserved, array $ledgerIds = []): self
    {
        return new self(
            success: true,
            requested: $requested,
            reserved: $reserved,
            ledgerIds: $ledgerIds,
            message: 'Quota successfully reserved.'
        );
    }

    public static function failure(string $message, int $requested = 0, int $reserved = 0): self
    {
        return new self(
            success: false,
            requested: $requested,
            reserved: $reserved,
            ledgerIds: [],
            message: $message
        );
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function isFailed(): bool
    {
        return ! $this->success;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'successful' => $this->success,
            'error' => $this->message,
            default => null,
        };
    }
}
