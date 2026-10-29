<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Enums\PingsFrom;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateOrgPingRequest extends FormRequest
{
    /**
     * Anyone in the org can ping it, as anyone in it can push to it, unless its maintainers kept pings to themselves
     * or turned them off.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $org = $this->route('org');
        assert($org instanceof Conversation);

        $role = $org->roleOf($user);

        return match (true) {
            ! $user->remotes_enabled || ! $role instanceof OrgRole => Response::denyAsNotFound(),
            ! $org->pings_from->allows($role) => Response::deny($org->pings_from === PingsFrom::Nobody ? 'Pings are off in this org.' : 'Only maintainers can ping this org.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
