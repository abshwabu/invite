<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tier_id',
        'cards_total',
        'cards_used',
        'starts_at',
        'expires_at',
        'status',
        'source',
        'payment_id',
        'note',
        'warnings_sent',
    ];

    protected function casts(): array
    {
        return [
            'cards_total' => 'integer',
            'cards_used' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'warnings_sent' => 'array',
        ];
    }

    /**
     * Check if a specific warning has already been sent for this subscription.
     */
    public function hasSentWarning(string $key): bool
    {
        $warnings = $this->warnings_sent ?? [];
        return in_array($key, $warnings, true);
    }

    /**
     * Record that a warning has been sent.
     */
    public function recordWarning(string $key): void
    {
        $warnings = $this->warnings_sent ?? [];
        if (! in_array($key, $warnings, true)) {
            $warnings[] = $key;
            $this->update(['warnings_sent' => $warnings]);
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function usageLedgers(): HasMany
    {
        return $this->hasMany(UsageLedger::class);
    }
}
