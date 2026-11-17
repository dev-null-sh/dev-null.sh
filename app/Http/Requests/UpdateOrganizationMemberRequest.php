<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

final class UpdateOrganizationMemberRequest extends FormRequest
{
    /**
     * Only admins change roles, and never to make themselves admins: they already are, and another admin making them a
     * member after this check would be undone.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return match (true) {
            ! $this->organization()->isMember($user) || ! $this->organization()->isMember($this->member()) => Response::denyAsNotFound(),
            ! $this->organization()->isAdmin($user) => Response::deny('Only admins can change roles.'),
            $user->is($this->member()) && $this->role() === OrganizationRole::Admin => Response::deny("You're already an admin."),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(OrganizationRole::class)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->role() === OrganizationRole::Member && $this->organization()->admins()->whereKeyNot($this->member()->id)->doesntExist()) {
                $validator->errors()->add('role', "@{$this->organization()->handle} needs at least one admin.");
            }
        }];
    }

    public function organization(): Organization
    {
        $organization = $this->route('organization');
        assert($organization instanceof Organization);

        return $organization;
    }

    public function member(): User
    {
        $member = $this->route('member');
        assert($member instanceof User);

        return $member;
    }

    public function role(): ?OrganizationRole
    {
        return $this->enum('role', OrganizationRole::class);
    }
}
