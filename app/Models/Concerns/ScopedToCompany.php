<?php

namespace App\Models\Concerns;

use App\Support\Organization\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;

trait ScopedToCompany
{
    protected static function bootScopedToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $companyId = app(CurrentOrganization::class)->id();

            if ($companyId) {
                $builder->where(
                    $builder->qualifyColumn('company_id'),
                    $companyId
                );
            }
        });
    }
}
