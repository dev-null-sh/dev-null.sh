<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowOrganizationProfileRequest extends FormRequest
{
    /**
     * Only the organization's admins edit its profile, so to everyone else, members included, the tab does not exist.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $organization = $this->route('organization');
        assert($organization instanceof Profile);

        return $organization->roleOf($user) === OrganizationRole::Admin ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
