<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\Services\GitHubProvider;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateApiOrganizationAdminRequest extends FormRequest
{
    private ?Organization $organization = null;

    /**
     * Only the first claim makes an admin, and only for someone GitHub lists as a public member of the organization,
     * which asks for no permission beyond sign-in's. Each refusal says why, as the web's page does.
     */
    public function authorize(GitHubProvider $github): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $organization = $this->organization();

        if (! $organization instanceof Organization) {
            return Response::denyAsNotFound();
        }

        $admin = $organization->admins()->oldest('organization_members.created_at')->first();

        if ($admin instanceof User) {
            return Response::deny(__('@:handle was already claimed by @:admin.', ['handle' => $organization->handle, 'admin' => $admin->handle]));
        }

        return $github->isPublicMember($organization->profile->github_id, $user->github_id)
            ? Response::allow()
            : Response::deny(__('GitHub does not list you as a public member of @:handle. On GitHub, make your membership of @:handle public, then try again.', ['handle' => $organization->handle]));
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Only organizations that already have a page, as the app finds them.
     */
    public function organization(): ?Organization
    {
        $handle = $this->route('handle');
        assert(is_string($handle));

        return $this->organization ??= Organization::query()
            ->whereRelation('profile', 'handle', $handle)
            ->first();
    }
}
