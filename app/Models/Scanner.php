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
}
