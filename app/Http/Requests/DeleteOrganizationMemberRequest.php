<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class DeleteOrganizationMemberRequest extends FormRequest
{
    /**
     * Any member can leave; only admins can remove someone else.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return match (true) {
            ! $this->organization()->roleOf($user) instanceof OrganizationRole || ! $this->organization()->roleOf($this->member()) instanceof OrganizationRole => Response::denyAsNotFound(),
            ! $user->is($this->member()) && $this->organization()->roleOf($user) !== OrganizationRole::Admin => Response::deny('Only admins can remove members.'),
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

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $isLastAdmin = $this->organization()->roleOf($this->member()) === OrganizationRole::Admin
                && $this->organization()->admins()->whereKeyNot($this->member()->id)->doesntExist();

            if ($isLastAdmin) {
                $validator->errors()->add('member', "@{$this->organization()->handle} needs at least one admin.");
            }
        }];
    }

    public function organization(): Profile
    {
        $organization = $this->route('organization');
        assert($organization instanceof Profile);

        return $organization;
    }

    public function member(): User
    {
        $member = $this->route('member');
        assert($member instanceof User);

        return $member;
    }
}
