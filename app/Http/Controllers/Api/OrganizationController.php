<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ShowApiOrganizationRequest;
use App\Http\Resources\OrganizationResource;

final readonly class OrganizationController
{
    /**
     * Organizations share the "@handle" pages with users, so the app asks for one when no user has the handle.
     */
    public function show(ShowApiOrganizationRequest $request): OrganizationResource
    {
        return OrganizationResource::make($request->organization());
    }
}
