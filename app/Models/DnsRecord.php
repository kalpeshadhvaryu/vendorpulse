<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DnsRecord extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'dns_domain_id',
        'type',
        'name',
        'content',
        'ttl',
        'priority',
        'proxied',
        'cf_record_id',
        'comment',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'ttl' => 'integer',
            'priority' => 'integer',
            'proxied' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(DnsDomain::class, 'dns_domain_id');
    }
}
