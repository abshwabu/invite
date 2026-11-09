<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'type',
        'channel',
        'subject',
        'body',
        'link_mode',
        'custom_url',
        'audience',
        'audience_count',
        'scheduled_at',
        'status',
        'estimated_cards',
    ];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'audience_count' => 'integer',
            'scheduled_at' => 'datetime',
            'estimated_cards' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function usageLedgers(): HasMany
    {
        return $this->hasMany(UsageLedger::class);
    }
}
