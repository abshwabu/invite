<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'code',
        'target_url',
        'clicks',
        'is_flagged',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'clicks' => 'integer',
            'is_flagged' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
