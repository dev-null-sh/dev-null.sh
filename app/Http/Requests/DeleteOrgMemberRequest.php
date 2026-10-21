<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteOrgMemberRequest extends FormRequest
{
    /**
     * Anyone can leave; only maintainers can remove someone else, which is checked before looking the member up, so
     * nobody learns whether a hidden sponsor is in the org. A sponsors org is turned off in settings rather than left
     * by its maintainer.
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
            ! $user->remotes_enabled || ! $org->roleOf($user) instanceof OrgRole => Response::denyAsNotFound(),
            ! $user->is($member) && ! $org->isMaintainedBy($user) => Response::deny('Only maintainers can remove people.'),
            ! $org->roleOf($member) instanceof OrgRole => Response::denyAsNotFound(),
            $org->isSponsors() && $org->isMaintainedBy($member) => Response::deny('Turn the sponsors org off in settings instead.'),
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
