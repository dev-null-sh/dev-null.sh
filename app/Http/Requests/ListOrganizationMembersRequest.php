<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListOrganizationMembersRequest extends FormRequest
{
    /**
     * Only the organization's members see who its members are. To everyone else, the settings don't exist.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $organization = $this->route('organization');
        assert($organization instanceof Profile);

        return $organization->roleOf($user) instanceof OrganizationRole ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
