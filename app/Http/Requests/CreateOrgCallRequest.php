<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateOrgCall;
use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\OrgCallQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateOrgCallRequest extends FormRequest
{
    /**
     * Anyone in the org can start its call, or join it while it has room.
     */
    public function authorize(OrgCallQuery $calls): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $org = $this->route('org');
        assert($org instanceof Conversation);

        if (! $user->remotes_enabled || ! $org->roleOf($user) instanceof OrgRole) {
            return Response::denyAsNotFound();
        }

        if ($calls->isInAnother($user, $org)) {
            return Response::deny("You're already on a call.");
        }

        $members = $calls->get($org)['members'] ?? [];

        return count($members) < CreateOrgCall::MAX_MEMBERS || isset($members[$user->id])
            ? Response::allow()
            : Response::deny('This call is full.');
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
