<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    /** @use HasFactory<\Database\Factories\TemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'kind',
        'name',
        'preview_path',
        'design_json',
        'palette',
        'tags',
        'recipe',
        'ornament_sets',
        'spec',
        'suite_key',
        'width',
        'height',
        'is_system',
        'user_id',
        'is_active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'design_json' => 'array',
            'palette' => 'array',
            'tags' => 'array',
            'ornament_sets' => 'array',
            'spec' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'template_id');
    }

    public function thankyouEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'thankyou_template_id');
    }
}
