<?php

namespace App\Services\Cloudflare;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudflareApiClient
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.cloudflare.api_token')) !== '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listZones(): array
    {
        $accountId = trim((string) config('services.cloudflare.account_id'));
        $page = 1;
        $zones = [];

        do {
            $query = ['page' => $page, 'per_page' => 50];
            if ($accountId !== '') {
                $query['account.id'] = $accountId;
            }

            $payload = $this->get('/zones', $query);
            $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
            foreach ($result as $zone) {
                if (is_array($zone)) {
                    $zones[] = $zone;
                }
            }

            $totalPages = (int) ($payload['result_info']['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $zones;
    }

    /**
     * @return array<string, mixed>
     */
    public function getZone(string $cfZoneId): array
    {
        $payload = $this->get('/zones/'.$cfZoneId);

        return is_array($payload['result'] ?? null) ? $payload['result'] : [];
    }

    public function getSslMode(string $cfZoneId): ?string
    {
        try {
            $payload = $this->get('/zones/'.$cfZoneId.'/settings/ssl');
            $value = $payload['result']['value'] ?? null;

            return is_string($value) ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function purgeEverything(string $cfZoneId): array
    {
        return $this->post('/zones/'.$cfZoneId.'/purge_cache', [
            'purge_everything' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $response = $this->http()->get(ltrim($path, '/'), $query);

        return $this->unwrap($response->json(), $response->status());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        $response = $this->http()->post(ltrim($path, '/'), $body);

        return $this->unwrap($response->json(), $response->status());
    }

    private function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Cloudflare is not configured. Set CLOUDFLARE_API_TOKEN (and optionally CLOUDFLARE_ACCOUNT_ID) in the environment.'
            );
        }

        $base = rtrim((string) config('services.cloudflare.api_base'), '/').'/';
        $timeout = (int) config('services.cloudflare.http_timeout_seconds', 20);

        return Http::baseUrl($base)
            ->timeout($timeout)
            ->acceptJson()
            ->withToken((string) config('services.cloudflare.api_token'));
    }

    /**
     * @return array<string, mixed>
     */
    private function unwrap(mixed $json, int $status): array
    {
        if (! is_array($json)) {
            throw new RuntimeException('Cloudflare returned an invalid response (HTTP '.$status.').');
        }

        if (($json['success'] ?? false) !== true) {
            $errors = $json['errors'] ?? [];
            $message = 'Cloudflare API error';
            if (is_array($errors) && isset($errors[0]['message'])) {
                $message .= ': '.$errors[0]['message'];
            }

            throw new RuntimeException($message.' (HTTP '.$status.')');
        }

        return $json;
    }
}
