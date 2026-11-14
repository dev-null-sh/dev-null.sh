<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListApiOrganizationMembersRequest extends FormRequest
{
    private ?Organization $organization = null;

    /**
     * Only the organization's members see who its members are. To everyone else, the list doesn't exist.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->organization()?->isMember($user) === true ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    public function organization(): ?Organization
    {
        $handle = $this->route('handle');
        assert(is_string($handle));

        return $this->organization ??= Organization::query()
            ->whereRelation('profile', 'handle', $handle)
            ->first();
    }
}
