<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CloudflareZone;
use App\Models\DnsDomain;
use App\Services\Dns\DnsDomainService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DnsDomainController extends BaseApiController
{
    public function __construct(private readonly DnsDomainService $dns) {}

    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId();

        $items = DnsDomain::query()
            ->with('cloudflareZone')
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get()
            ->map(fn (DnsDomain $d) => $this->dns->presentDomain($d));

        $cloudflareZones = CloudflareZone::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name', 'status'])
            ->map(fn (CloudflareZone $z) => [
                'id' => $z->id,
                'name' => $z->name,
                'status' => $z->status,
            ]);

        return ApiResponse::success([
            'items' => $items,
            'cloudflare_configured' => $this->dnsCloudflareReady(),
            'available_cloudflare_zones' => $cloudflareZones,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId();

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in([DnsDomain::PROVIDER_CLOUDFLARE, DnsDomain::PROVIDER_MANUAL])],
            'name' => ['nullable', 'string', 'max:255'],
            'cloudflare_zone_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $domain = $this->dns->create($organizationId, $request->user()?->id, $validated);
        } catch (Throwable $e) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }

            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return ApiResponse::success($this->dns->presentDomain($domain->load('cloudflareZone')), 'DNS domain added.', Response::HTTP_CREATED);
    }

    public function show(Request $request, DnsDomain $dnsDomain): JsonResponse
    {
        $this->assertDomainOrg($dnsDomain);

        return ApiResponse::success($this->dns->presentDomain($dnsDomain->load('cloudflareZone')));
    }

    public function update(Request $request, DnsDomain $dnsDomain): JsonResponse
    {
        $this->assertDomainOrg($dnsDomain);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $dnsDomain->update($validated);

        return ApiResponse::success($this->dns->presentDomain($dnsDomain->fresh()->load('cloudflareZone')), 'DNS domain updated.');
    }

    public function destroy(Request $request, DnsDomain $dnsDomain): JsonResponse
    {
        $this->assertDomainOrg($dnsDomain);
        $this->dns->deleteDomain($dnsDomain);

        return ApiResponse::success(['deleted' => true], 'DNS domain removed from VendorPulse.');
    }

    public function records(Request $request, DnsDomain $dnsDomain): JsonResponse
    {
        $this->assertDomainOrg($dnsDomain);

        try {
            $items = $this->dns->listRecords($dnsDomain);
        } catch (Throwable $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        return ApiResponse::success([
            'domain' => $this->dns->presentDomain($dnsDomain->load('cloudflareZone')),
            'items' => $items,
        ]);
    }

    public function storeRecord(Request $request, DnsDomain $dnsDomain): JsonResponse
    {
        $this->assertDomainOrg($dnsDomain);

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:16'],
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:4000'],
            'ttl' => ['nullable', 'integer'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'proxied' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $record = $this->dns->createRecord($dnsDomain, $validated);
        } catch (Throwable $e) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }

            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        return ApiResponse::success($record, 'DNS record created.', Response::HTTP_CREATED);
    }

    public function updateRecord(Request $request, DnsDomain $dnsDomain, string $dns_record): JsonResponse
    {
        $this->assertDomainOrg($dnsDomain);

        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'max:16'],
            'name' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string', 'max:4000'],
            'ttl' => ['nullable', 'integer'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'proxied' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $record = $this->dns->updateRecord($dnsDomain, $dns_record, $validated);
        } catch (Throwable $e) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }

            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        return ApiResponse::success($record, 'DNS record updated.');
    }

    public function destroyRecord(Request $request, DnsDomain $dnsDomain, string $dns_record): JsonResponse
    {
        $this->assertDomainOrg($dnsDomain);

        try {
            $this->dns->deleteRecord($dnsDomain, $dns_record);
        } catch (Throwable $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        return ApiResponse::success(['deleted' => true], 'DNS record deleted.');
    }

    private function assertDomainOrg(DnsDomain $domain): void
    {
        if ((string) $domain->organization_id !== $this->organizationId()) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    private function dnsCloudflareReady(): bool
    {
        return app(\App\Services\Cloudflare\CloudflareApiClient::class)->isConfigured();
    }
}
