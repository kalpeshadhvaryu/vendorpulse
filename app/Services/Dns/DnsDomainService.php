<?php

namespace App\Services\Dns;

use App\Models\CloudflareZone;
use App\Models\DnsDomain;
use App\Models\DnsRecord;
use App\Services\Cloudflare\CloudflareApiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DnsDomainService
{
    /** @var list<string> */
    public const RECORD_TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'PTR'];

    public function __construct(private readonly CloudflareApiClient $cloudflare) {}

    /**
     * @return array<string, mixed>
     */
    public function presentDomain(DnsDomain $domain): array
    {
        $domain->loadMissing('cloudflareZone');

        return [
            'id' => $domain->id,
            'organization_id' => $domain->organization_id,
            'name' => $domain->name,
            'provider' => $domain->provider,
            'cloudflare_zone_id' => $domain->cloudflare_zone_id,
            'cloudflare_zone_name' => $domain->cloudflareZone?->name,
            'notes' => $domain->notes,
            'created_at' => optional($domain->created_at)?->toIso8601String(),
            'updated_at' => optional($domain->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRecord(DnsRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => $record->type,
            'name' => $record->name,
            'content' => $record->content,
            'ttl' => $record->ttl,
            'priority' => $record->priority,
            'proxied' => $record->proxied,
            'comment' => $record->comment,
            'cf_record_id' => $record->cf_record_id,
            'source' => 'manual',
            'created_at' => optional($record->created_at)?->toIso8601String(),
            'updated_at' => optional($record->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $cf
     * @return array<string, mixed>
     */
    public function presentCloudflareRecord(array $cf): array
    {
        return [
            'id' => (string) ($cf['id'] ?? ''),
            'type' => strtoupper((string) ($cf['type'] ?? '')),
            'name' => (string) ($cf['name'] ?? ''),
            'content' => (string) ($cf['content'] ?? ''),
            'ttl' => (int) ($cf['ttl'] ?? 1),
            'priority' => isset($cf['priority']) ? (int) $cf['priority'] : null,
            'proxied' => array_key_exists('proxied', $cf) ? (bool) $cf['proxied'] : null,
            'comment' => isset($cf['comment']) ? (string) $cf['comment'] : null,
            'cf_record_id' => (string) ($cf['id'] ?? ''),
            'source' => 'cloudflare',
            'created_at' => isset($cf['created_on']) ? (string) $cf['created_on'] : null,
            'updated_at' => isset($cf['modified_on']) ? (string) $cf['modified_on'] : null,
        ];
    }

    /**
     * @param  array{provider: string, name?: string|null, cloudflare_zone_id?: string|null, notes?: string|null}  $data
     */
    public function create(string $organizationId, ?string $userId, array $data): DnsDomain
    {
        $provider = strtolower((string) $data['provider']);

        if ($provider === DnsDomain::PROVIDER_CLOUDFLARE) {
            $zoneId = (string) ($data['cloudflare_zone_id'] ?? '');
            $zone = CloudflareZone::query()
                ->whereKey($zoneId)
                ->where('organization_id', $organizationId)
                ->first();

            if (! $zone) {
                throw ValidationException::withMessages([
                    'cloudflare_zone_id' => 'Select a Cloudflare domain assigned to this organization.',
                ]);
            }

            $existing = DnsDomain::query()
                ->where('organization_id', $organizationId)
                ->where(function ($q) use ($zone): void {
                    $q->where('cloudflare_zone_id', $zone->id)
                        ->orWhere('name', $zone->name);
                })
                ->first();

            if ($existing) {
                return $existing;
            }

            return DnsDomain::query()->create([
                'organization_id' => $organizationId,
                'name' => $zone->name,
                'provider' => DnsDomain::PROVIDER_CLOUDFLARE,
                'cloudflare_zone_id' => $zone->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);
        }

        if ($provider !== DnsDomain::PROVIDER_MANUAL) {
            throw ValidationException::withMessages([
                'provider' => 'Provider must be cloudflare or manual.',
            ]);
        }

        $name = strtolower(trim((string) ($data['name'] ?? '')));
        $name = rtrim($name, '.');

        if ($name === '' || ! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $name)) {
            throw ValidationException::withMessages([
                'name' => 'Enter a valid domain name (e.g. example.com).',
            ]);
        }

        if (DnsDomain::query()->where('organization_id', $organizationId)->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'name' => 'This domain is already registered for the organization.',
            ]);
        }

        return DnsDomain::query()->create([
            'organization_id' => $organizationId,
            'name' => $name,
            'provider' => DnsDomain::PROVIDER_MANUAL,
            'cloudflare_zone_id' => null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecords(DnsDomain $domain): array
    {
        if ($domain->isCloudflare()) {
            $cfZoneId = $this->requireCfZoneId($domain);
            $remote = $this->cloudflare->listDnsRecords($cfZoneId);

            return array_map(fn (array $r) => $this->presentCloudflareRecord($r), $remote);
        }

        return $domain->records()
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (DnsRecord $r) => $this->presentRecord($r))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createRecord(DnsDomain $domain, array $data): array
    {
        $payload = $this->normalizeRecordPayload($data, forUpdate: false);

        if ($domain->isCloudflare()) {
            $cfZoneId = $this->requireCfZoneId($domain);
            $created = $this->cloudflare->createDnsRecord($cfZoneId, $this->toCloudflareBody($payload));

            return $this->presentCloudflareRecord($created);
        }

        $record = $domain->records()->create([
            'type' => $payload['type'],
            'name' => $payload['name'],
            'content' => $payload['content'],
            'ttl' => $payload['ttl'],
            'priority' => $payload['priority'],
            'proxied' => null,
            'comment' => $payload['comment'],
        ]);

        return $this->presentRecord($record);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateRecord(DnsDomain $domain, string $recordId, array $data): array
    {
        $payload = $this->normalizeRecordPayload($data, forUpdate: true);

        if ($domain->isCloudflare()) {
            $cfZoneId = $this->requireCfZoneId($domain);
            $body = $this->toCloudflareBody($payload);
            $updated = $this->cloudflare->updateDnsRecord($cfZoneId, $recordId, $body);

            return $this->presentCloudflareRecord($updated);
        }

        $record = $domain->records()->whereKey($recordId)->firstOrFail();
        $record->fill(array_filter([
            'type' => $payload['type'] ?? null,
            'name' => $payload['name'] ?? null,
            'content' => $payload['content'] ?? null,
            'ttl' => $payload['ttl'] ?? null,
            'priority' => array_key_exists('priority', $payload) ? $payload['priority'] : $record->priority,
            'comment' => array_key_exists('comment', $payload) ? $payload['comment'] : $record->comment,
        ], fn ($v) => $v !== null));
        $record->save();

        return $this->presentRecord($record->fresh());
    }

    public function deleteRecord(DnsDomain $domain, string $recordId): void
    {
        if ($domain->isCloudflare()) {
            $cfZoneId = $this->requireCfZoneId($domain);
            $this->cloudflare->deleteDnsRecord($cfZoneId, $recordId);

            return;
        }

        $domain->records()->whereKey($recordId)->firstOrFail()->delete();
    }

    public function deleteDomain(DnsDomain $domain): void
    {
        DB::transaction(function () use ($domain): void {
            $domain->records()->delete();
            $domain->delete();
        });
    }

    private function requireCfZoneId(DnsDomain $domain): string
    {
        $domain->loadMissing('cloudflareZone');
        $cfZoneId = $domain->cloudflareZone?->cf_zone_id;

        if (! $cfZoneId) {
            throw new RuntimeException('This DNS domain is not linked to a Cloudflare zone.');
        }

        if (! $this->cloudflare->isConfigured()) {
            throw new RuntimeException('Cloudflare API token is not configured.');
        }

        return $cfZoneId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeRecordPayload(array $data, bool $forUpdate): array
    {
        $out = [];

        if (array_key_exists('type', $data) || ! $forUpdate) {
            $type = strtoupper(trim((string) ($data['type'] ?? '')));
            if (! in_array($type, self::RECORD_TYPES, true)) {
                throw ValidationException::withMessages([
                    'type' => 'Unsupported DNS record type.',
                ]);
            }
            $out['type'] = $type;
        }

        if (array_key_exists('name', $data) || ! $forUpdate) {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Record name is required.']);
            }
            $out['name'] = $name;
        }

        if (array_key_exists('content', $data) || ! $forUpdate) {
            $content = trim((string) ($data['content'] ?? ''));
            if ($content === '') {
                throw ValidationException::withMessages(['content' => 'Record content is required.']);
            }
            $out['content'] = $content;
        }

        if (array_key_exists('ttl', $data) || ! $forUpdate) {
            $ttl = (int) ($data['ttl'] ?? 3600);
            if ($ttl !== 1 && ($ttl < 60 || $ttl > 86400)) {
                throw ValidationException::withMessages([
                    'ttl' => 'TTL must be 1 (auto) or between 60 and 86400.',
                ]);
            }
            $out['ttl'] = $ttl;
        }

        if (array_key_exists('priority', $data)) {
            $out['priority'] = $data['priority'] === null || $data['priority'] === ''
                ? null
                : (int) $data['priority'];
        } elseif (! $forUpdate) {
            $out['priority'] = isset($data['priority']) ? (int) $data['priority'] : null;
        }

        if (array_key_exists('proxied', $data)) {
            $out['proxied'] = (bool) $data['proxied'];
        }

        if (array_key_exists('comment', $data)) {
            $comment = $data['comment'];
            $out['comment'] = $comment === null || $comment === '' ? null : (string) $comment;
        } elseif (! $forUpdate) {
            $out['comment'] = null;
        }

        $type = $out['type'] ?? null;
        if ($type === 'MX' && ! $forUpdate && ($out['priority'] ?? null) === null) {
            $out['priority'] = 10;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function toCloudflareBody(array $payload): array
    {
        $body = [];
        foreach (['type', 'name', 'content', 'ttl', 'priority', 'proxied', 'comment'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null) {
                $body[$key] = $payload[$key];
            }
        }

        return $body;
    }
}
