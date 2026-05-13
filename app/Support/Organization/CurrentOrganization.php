<?php

namespace App\Support\Organization;

use App\Models\Organization;

class CurrentOrganization
{
    protected ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?string
    {
        return $this->organization?->getKey();
    }

    public function clear(): void
    {
        $this->organization = null;
    }
}
