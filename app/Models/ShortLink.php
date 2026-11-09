<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShortLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'target_url',
        'clicks',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'clicks' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
