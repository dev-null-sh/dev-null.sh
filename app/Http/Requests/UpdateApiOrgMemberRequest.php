<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

final class UpdateApiOrgMemberRequest extends FormRequest
{
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
            ! $org->isMaintainedBy($user) => Response::deny('Only maintainers can change roles.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(OrgRole::class)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $org = $this->route('org');
            assert($org instanceof Conversation);

            $member = $this->route('member');
            assert($member instanceof User);

            $isLastMaintainer = $org->isMaintainedBy($member)
                && $org->participants()->where('role', OrgRole::Maintainer)->count() === 1;

            if ($this->role() === OrgRole::Contributor && $isLastMaintainer) {
                $validator->errors()->add('role', 'An org needs at least one maintainer.');
            }
        }];
    }

    public function role(): ?OrgRole
    {
        return $this->enum('role', OrgRole::class);
    }
}
