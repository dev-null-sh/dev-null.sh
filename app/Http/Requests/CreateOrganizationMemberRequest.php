<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Organization;
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

        return match (true) {
            ! $this->organization()->isMember($user) => Response::denyAsNotFound(),
            ! $this->organization()->isAdmin($user) => Response::deny('Only admins can add members.'),
            default => Response::allow(),
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
            } elseif ($this->organization()->isMember($member)) {
                $validator->errors()->add('handle', "{$member->mention()} is already a member.");
            }
        }];
    }

    public function organization(): Organization
    {
        $organization = $this->route('organization');
        assert($organization instanceof Organization);

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
