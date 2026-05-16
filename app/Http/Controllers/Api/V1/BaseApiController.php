<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Organization\CurrentOrganization;

abstract class BaseApiController extends Controller
{
    protected function organizationIdOrNull(): ?string
    {
        return app(CurrentOrganization::class)->id();
    }

    protected function organizationId(): string
    {
        $id = $this->organizationIdOrNull();

        if (! $id) {
            abort(422, 'Organization context missing. Send X-Organization-Id or select a specific organization.');
        }

        return $id;
    }
}
