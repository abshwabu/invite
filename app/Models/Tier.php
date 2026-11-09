<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tier extends Model
{
    /** @use HasFactory<\Database\Factories\TierFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name_en',
        'name_am',
        'description_en',
        'description_am',
        'price_amount',
        'currency',
        'card_quota',
        'validity_days',
        'max_events',
        'max_guests_per_event',
        'max_scanners_per_event',
        'sms_weight',
        'email_weight',
        'thank_you_consumes_quota',
        'features',
        'sort',
        'is_active',
        'is_public',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'decimal:2',
            'card_quota' => 'integer',
            'validity_days' => 'integer',
            'max_events' => 'integer',
            'max_guests_per_event' => 'integer',
            'max_scanners_per_event' => 'integer',
            'sms_weight' => 'integer',
            'email_weight' => 'integer',
            'thank_you_consumes_quota' => 'boolean',
            'features' => 'array',
            'sort' => 'integer',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
