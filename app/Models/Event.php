<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'timezone',
        'venue_name',
        'venue_address',
        'map_url',
        'host_names',
        'is_invite_only',
        'rsvp_enabled',
        'plus_ones_allowed',
        'design_json',
        'template_id',
        'thankyou_design_json',
        'thankyou_template_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_invite_only' => 'boolean',
            'rsvp_enabled' => 'boolean',
            'plus_ones_allowed' => 'boolean',
            'design_json' => 'array',
            'thankyou_design_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function thankyouTemplate(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'thankyou_template_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(EventMember::class);
    }

    public function memberUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function scanners(): HasMany
    {
        return $this->hasMany(Scanner::class);
    }

    public function checkinLogs(): HasMany
    {
        return $this->hasMany(CheckinLog::class);
    }

    public function usageLedgers(): HasMany
    {
        return $this->hasMany(UsageLedger::class);
    }
}
