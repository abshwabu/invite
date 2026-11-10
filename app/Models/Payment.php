<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\URL;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tier_id',
        'payment_code',
        'amount',
        'currency',
        'provider',
        'provider_ref',
        'status',
        'method_id',
        'reference_number',
        'receipt_path',
        'receipt_hash',
        'receipt_phash',
        'amount_expected',
        'amount_declared',
        'amount_verified',
        'paid_at_declared',
        'paid_from',
        'user_note',
        'admin_note',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'submitted_at',
        'flags',
        'payload',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_expected' => 'decimal:2',
            'amount_declared' => 'decimal:2',
            'amount_verified' => 'decimal:2',
            'paid_at_declared' => 'datetime',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'paid_at' => 'datetime',
            'flags' => 'array',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'method_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'reviewed_by');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('created_at', 'asc');
    }

    public function isAwaitingProof(): bool
    {
        return $this->status === 'awaiting_proof';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isNeedsInfo(): bool
    {
        return $this->status === 'needs_info';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isAbandoned(): bool
    {
        return $this->status === 'abandoned';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function hasFlag(string $flag): bool
    {
        return !empty($this->flags[$flag]);
    }

    /**
     * Get short-lived signed URL to access the private receipt image.
     */
    public function getReceiptUrl(): ?string
    {
        if (!$this->receipt_path) {
            return null;
        }

        return URL::temporarySignedRoute(
            'payments.receipt',
            now()->addMinutes(60),
            ['payment' => $this->id]
        );
    }
}
