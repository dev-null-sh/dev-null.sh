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

final class UpdateApiOrganizationMemberRequest extends FormRequest
{
    private ?Organization $organization = null;

    /**
     * Only admins change roles, and never to make themselves admins: they already are, and another admin making them a
     * member after this check would be undone.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $organization = $this->organization();

        return match (true) {
            ! $organization instanceof Organization || ! $organization->isMember($user) || ! $organization->isMember($this->member()) => Response::denyAsNotFound(),
            ! $organization->isAdmin($user) => Response::deny('Only admins can change roles.'),
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
            $organization = $this->organization();
            assert($organization instanceof Organization);

            if ($this->role() === OrganizationRole::Member && $organization->admins()->whereKeyNot($this->member()->id)->doesntExist()) {
                $validator->errors()->add('role', "@{$organization->handle} needs at least one admin.");
            }
        }];
    }

    public function organization(): ?Organization
    {
        $handle = $this->route('handle');
        assert(is_string($handle));

        return $this->organization ??= Organization::query()
            ->whereRelation('profile', 'handle', $handle)
            ->first();
    }

    public function member(): User
    {
        $member = $this->route('member');
        assert($member instanceof User);

        return $member;
    }

    /**
     * Null for anything but a string, which the enum rule then rejects, as authorization reads it before validation.
     */
    public function role(): ?OrganizationRole
    {
        return is_string($this->input('role')) ? $this->enum('role', OrganizationRole::class) : null;
    }
}
