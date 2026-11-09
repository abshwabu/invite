<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Guest extends Model
{
    /** @use HasFactory<\Database\Factories\GuestFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Guest $guest) {
            if (empty($guest->token)) {
                $guest->token = Str::random(32);
            }
        });
    }

    protected $fillable = [
        'event_id',
        'name',
        'phone',
        'email',
        'token',
        'preferred_channel',
        'max_party_size',
        'rsvp_status',
        'rsvp_party_size',
        'rsvp_note',
        'opted_out',
        'viewed_at',
        'checked_in_at',
        'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'max_party_size' => 'integer',
            'rsvp_party_size' => 'integer',
            'opted_out' => 'boolean',
            'viewed_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function checkinLogs(): HasMany
    {
        return $this->hasMany(CheckinLog::class);
    }

    public function usageLedgers(): HasMany
    {
        return $this->hasMany(UsageLedger::class);
    }

    public function checkedInByScanner(): BelongsTo
    {
        return $this->belongsTo(Scanner::class, 'checked_in_by');
    }
}
