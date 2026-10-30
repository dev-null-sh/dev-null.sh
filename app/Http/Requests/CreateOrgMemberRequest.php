<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\RemoteCandidatesQuery;
use App\Queries\UsersByHandleQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

final class CreateOrgMemberRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return match (true) {
            ! $user->remotes_enabled || ! $this->org()->roleOf($user) instanceof OrgRole => Response::denyAsNotFound(),
            ! $this->org()->isMaintainedBy($user) => Response::deny('Only maintainers can add people.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'members' => ['required', 'array', 'min:1', 'max:'.(Conversation::MAX_MEMBERS - 1)],
            'members.*' => ['required', 'string', 'distinct:ignore_case'],
            'share_history' => ['required', 'boolean'],
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

            $members = $this->members();

            foreach (array_keys($this->array('members')) as $index) {
                if (! $members->has($index)) {
                    $validator->addFailure("members.{$index}", 'exists');
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            assert($user instanceof User);

            $reasons = resolve(RemoteCandidatesQuery::class)->reasons($user, $this->members(), $this->org(), $this->members());

            foreach ($this->members() as $index => $member) {
                if ($reasons[$member->id] !== null) {
                    $validator->errors()->add("members.{$index}", "{$member->mention()} {$reasons[$member->id]}.");
                }
            }

            if ($this->org()->participants()->count() + $this->members()->count() > Conversation::MAX_MEMBERS) {
                $validator->errors()->add('members', 'An org holds up to '.Conversation::MAX_MEMBERS.' people.');
            }
        }];
    }

    public function org(): Conversation
    {
        $org = $this->route('org');
        assert($org instanceof Conversation);

        return $org;
    }

    /**
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        /** @var list<string> $handles */
        $handles = $this->array('members');

        return resolve(UsersByHandleQuery::class)->get($handles);
    }
}
