<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EmailMailbox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EmailMailbox */
class EmailMailboxResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $raw = $this->connection_config ?? [];
        $config = is_array($raw) ? $raw : [];

        $public = $config;
        if (isset($public['password'])) {
            $public['password'] = null;
        }
        if (isset($public['client_secret'])) {
            $public['client_secret'] = null;
        }
        if (isset($public['refresh_token'])) {
            $public['refresh_token'] = null;
        }

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'driver' => $this->driver instanceof \BackedEnum ? $this->driver->value : $this->driver,
            'is_enabled' => $this->is_enabled,
            'connection_config' => $public,
            'has_password' => isset($config['password']) && is_string($config['password']) && $config['password'] !== '',
            'has_client_secret' => isset($config['client_secret']) && is_string($config['client_secret']) && $config['client_secret'] !== '',
            'has_refresh_token' => isset($config['refresh_token']) && is_string($config['refresh_token']) && $config['refresh_token'] !== '',
            'last_polled_at' => $this->last_polled_at?->toIso8601String(),
            'last_successful_sync_at' => $this->last_successful_sync_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
