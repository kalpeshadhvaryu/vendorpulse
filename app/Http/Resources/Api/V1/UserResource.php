<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'timezone' => $this->timezone,
            'default_organization_id' => $this->default_organization_id,
            'organizations' => OrganizationResource::collection($this->whenLoaded('organizations')),
            'default_organization' => new OrganizationResource($this->whenLoaded('defaultOrganization')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
