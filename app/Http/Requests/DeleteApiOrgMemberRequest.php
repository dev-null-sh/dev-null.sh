<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteApiOrgMemberRequest extends FormRequest
{
    /**
     * Anyone can leave; only maintainers can remove someone else.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $org = $this->route('org');
        assert($org instanceof Conversation);

        $member = $this->route('member');
        assert($member instanceof User);

        return match (true) {
            ! $user->remotes_enabled || ! $org->roleOf($user) instanceof OrgRole || ! $org->roleOf($member) instanceof OrgRole => Response::denyAsNotFound(),
            ! $user->is($member) && ! $org->isMaintainedBy($user) => Response::deny('Only maintainers can remove people.'),
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
