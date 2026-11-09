<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scanner extends Model
{
    /** @use HasFactory<\Database\Factories\ScannerFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'pin_hash',
        'token',
        'revoked_at',
        'failed_attempts',
        'locked_until',
        'last_used_at',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_used_at' => 'datetime',
            'failed_attempts' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function checkinLogs(): HasMany
    {
        return $this->hasMany(CheckinLog::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function remainingLockoutMinutes(): int
    {
        if (! $this->isLocked()) {
            return 0;
        }

        return max(1, (int) ceil(now()->diffInSeconds($this->locked_until) / 60));
    }

    public function recordFailedAttempt(): void
    {
        $this->increment('failed_attempts');
        if ($this->failed_attempts >= 5) {
            $this->update(['locked_until' => now()->addMinutes(15)]);
        }
    }

    public function recordSuccessfulLogin(): void
    {
        $this->update([
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_used_at' => now(),
        ]);
    }
}
