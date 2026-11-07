<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateOrganizationPeopleVisibilityRequest extends FormRequest
{
    /**
     * Only the organization's admins choose who sees its members, so to everyone else it looks like it does not exist.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $organization = $this->route('organization');
        assert($organization instanceof Organization);

        return $organization->isAdmin($user) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'shows_people' => ['required', 'boolean'],
        ];
    }

    public function showsPeople(): bool
    {
        return $this->boolean('shows_people');
    }
}
