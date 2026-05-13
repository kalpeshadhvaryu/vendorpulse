<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Organization\CurrentOrganization;

abstract class BaseApiController extends Controller
{
    protected function organizationId(): string
    {
        $id = app(CurrentOrganization::class)->id();

        if (! $id) {
            abort(422, 'Organization context missing.');
        }

        return $id;
    }
}
