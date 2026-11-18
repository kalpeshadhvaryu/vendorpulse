<?php

namespace App\Services\Cloudflare;

use App\Models\CloudflareZone;
use Illuminate\Support\Carbon;

class CloudflareZoneService
{
    public function __construct(private readonly CloudflareApiClient $api) {}

    public function isConfigured(): bool
    {
        return $this->api->isConfigured();
    }

    /**
     * Pull zones from the company Cloudflare account into the local catalog.
     *
     * @return array{synced:int, created:int, updated:int}
     */
    public function syncFromCloudflare(): array
    {
        $remoteZones = $this->api->listZones();
        $created = 0;
        $updated = 0;
        $now = now();

        foreach ($remoteZones as $remote) {
            $cfZoneId = (string) ($remote['id'] ?? '');
            $name = strtolower((string) ($remote['name'] ?? ''));
            if ($cfZoneId === '' || $name === '') {
                continue;
            }

            $existing = CloudflareZone::query()->where('cf_zone_id', $cfZoneId)->first();
            $attrs = [
                'name' => $name,
                'status' => isset($remote['status']) ? (string) $remote['status'] : null,
                'paused' => (bool) ($remote['paused'] ?? false),
                'plan_name' => data_get($remote, 'plan.name'),
                'name_servers' => is_array($remote['name_servers'] ?? null) ? $remote['name_servers'] : null,
                'meta' => [
                    'account' => data_get($remote, 'account'),
                    'type' => $remote['type'] ?? null,
                    'original_name_servers' => $remote['original_name_servers'] ?? null,
                ],
                'last_synced_at' => $now,
            ];

            if ($existing) {
                $existing->update($attrs);
                $updated++;
            } else {
                CloudflareZone::query()->create([
                    ...$attrs,
                    'cf_zone_id' => $cfZoneId,
                ]);
                $created++;
            }
        }

        return [
            'synced' => $created + $updated,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    public function refresh(CloudflareZone $zone): CloudflareZone
    {
        $remote = $this->api->getZone($zone->cf_zone_id);
        $sslMode = $this->api->getSslMode($zone->cf_zone_id);

        $zone->update([
            'name' => strtolower((string) ($remote['name'] ?? $zone->name)),
            'status' => isset($remote['status']) ? (string) $remote['status'] : $zone->status,
            'paused' => (bool) ($remote['paused'] ?? $zone->paused),
            'plan_name' => data_get($remote, 'plan.name') ?? $zone->plan_name,
            'name_servers' => is_array($remote['name_servers'] ?? null) ? $remote['name_servers'] : $zone->name_servers,
            'ssl_mode' => $sslMode ?? $zone->ssl_mode,
            'ssl_status' => $sslMode ? 'configured' : $zone->ssl_status,
            'meta' => array_merge($zone->meta ?? [], [
                'account' => data_get($remote, 'account'),
                'type' => $remote['type'] ?? null,
            ]),
            'last_synced_at' => now(),
        ]);

        return $zone->fresh();
    }

    public function assign(CloudflareZone $zone, ?string $organizationId, ?string $userId): CloudflareZone
    {
        $zone->update([
            'organization_id' => $organizationId,
            'assigned_by' => $organizationId ? $userId : null,
            'assigned_at' => $organizationId ? Carbon::now() : null,
        ]);

        return $zone->fresh();
    }

    public function purgeCache(CloudflareZone $zone): array
    {
        return $this->api->purgeEverything($zone->cf_zone_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(CloudflareZone $zone, bool $includeAssignment = true): array
    {
        $data = [
            'id' => $zone->id,
            'cf_zone_id' => $zone->cf_zone_id,
            'name' => $zone->name,
            'status' => $zone->status,
            'paused' => $zone->paused,
            'plan_name' => $zone->plan_name,
            'name_servers' => $zone->name_servers ?? [],
            'ssl_mode' => $zone->ssl_mode,
            'ssl_status' => $zone->ssl_status,
            'last_synced_at' => optional($zone->last_synced_at)?->toIso8601String(),
            'created_at' => optional($zone->created_at)?->toIso8601String(),
            'updated_at' => optional($zone->updated_at)?->toIso8601String(),
        ];

        if ($includeAssignment) {
            $data['organization_id'] = $zone->organization_id;
            $data['organization_name'] = $zone->organization?->name;
            $data['assigned_at'] = optional($zone->assigned_at)?->toIso8601String();
        }

        return $data;
    }
}
