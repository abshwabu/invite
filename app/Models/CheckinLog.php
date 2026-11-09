<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckinLog extends Model
{
    /** @use HasFactory<\Database\Factories\CheckinLogFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'guest_id',
        'scanner_id',
        'result',
        'raw_payload',
        'ip_address',
        'device_info',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(Scanner::class);
    }
}
