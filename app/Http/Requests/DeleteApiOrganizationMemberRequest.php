<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class DeleteApiOrganizationMemberRequest extends FormRequest
{
    private ?Organization $organization = null;

    /**
     * Any member can leave; only admins can remove someone else.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $organization = $this->organization();

        return match (true) {
            ! $organization instanceof Organization || ! $organization->isMember($user) || ! $organization->isMember($this->member()) => Response::denyAsNotFound(),
            ! $user->is($this->member()) && ! $organization->isAdmin($user) => Response::deny('Only admins can remove members.'),
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
            $organization = $this->organization();
            assert($organization instanceof Organization);

            $isLastAdmin = $organization->isAdmin($this->member())
                && $organization->admins()->whereKeyNot($this->member()->id)->doesntExist();

            if ($isLastAdmin) {
                $validator->errors()->add('member', "@{$organization->handle} needs at least one admin.");
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
}
