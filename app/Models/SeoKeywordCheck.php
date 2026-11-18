<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoKeywordCheck extends Model
{
    use HasUuidPrimaryKey, ScopedToOrganization;

    protected $fillable = [
        'seo_keyword_id',
        'organization_id',
        'checked_at',
        'source',
        'provider',
        'google_position',
        'google_url',
        'ai_results',
        'meta',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'ai_results' => 'array',
            'meta' => 'array',
            'google_position' => 'integer',
        ];
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'seo_keyword_id');
    }
}
