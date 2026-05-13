<?php

namespace App\Models\Concerns;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;

trait ScopedToOrganization
{
    protected static function bootScopedToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            $orgId = app(CurrentOrganization::class)->id();

            if ($orgId) {
                $builder->where(
                    $builder->qualifyColumn('organization_id'),
                    $orgId
                );
            }
        });
    }
}
