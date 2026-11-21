<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\UpdateOrganizationLane;
use App\Http\Requests\UpdateApiOrganizationLaneRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;

final readonly class OrganizationLaneController
{
    public function update(UpdateApiOrganizationLaneRequest $request, UpdateOrganizationLane $action): OrganizationResource
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        $action->handle($organization, $request->lane(), $request->color());

        return new OrganizationResource($organization);
    }
}
