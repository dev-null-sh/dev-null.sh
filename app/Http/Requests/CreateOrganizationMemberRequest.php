<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CreateOrganizationMemberRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return match ($this->organization()->roleOf($user)) {
            null => Response::denyAsNotFound(),
            OrganizationRole::Member => Response::deny('Only admins can add members.'),
            OrganizationRole::Admin => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'handle' => ['required', 'string', 'max:40'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            assert($user instanceof User);

            $member = $this->member();

            if (! $member instanceof User) {
                $validator->errors()->add('handle', "There's no @{$this->handle()} on /dev/null.");
            } elseif ($user->hasBlockWith($member)) {
                $validator->errors()->add('handle', "You can't add {$member->mention()}.");
            } elseif ($this->organization()->roleOf($member) instanceof OrganizationRole) {
                $validator->errors()->add('handle', "{$member->mention()} is already a member.");
            }
        }];
    }

    public function organization(): Profile
    {
        $organization = $this->route('organization');
        assert($organization instanceof Profile);

        return $organization;
    }

    /**
     * The user at the handle, with or without its "@", resolved like "@handle" URLs, whatever its case.
     */
    public function member(): ?User
    {
        $member = (new User)->resolveRouteBinding($this->handle(), 'handle');

        return $member instanceof User ? $member : null;
    }

    private function handle(): string
    {
        return $this->string('handle')->trim()->ltrim('@')->value();
    }
}
