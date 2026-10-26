<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ConversationKind;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\RemoteCandidatesQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

final class CreateApiOrgRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->remotes_enabled ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:60'],
            'members' => ['required', 'array', 'min:1', 'max:'.(Conversation::MAX_MEMBERS - 1)],
            'members.*' => ['required', 'string', 'distinct', 'exists:users,username'],
        ];
    }

    /**
     * Each member who can't join says why, the way the web's picker does.
     *
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

            $reasons = resolve(RemoteCandidatesQuery::class)->reasons($user, $this->members(), new Conversation(['kind' => ConversationKind::Org]), $this->members());

            foreach ($this->members() as $index => $member) {
                if ($reasons[$member->id] !== null) {
                    $validator->errors()->add("members.{$index}", "@{$member->username} {$reasons[$member->id]}.");
                }
            }
        }];
    }

    /**
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        /** @var list<string> $usernames */
        $usernames = $this->array('members');

        return User::query()->whereIn('username', $usernames)->get()->sortBy(fn (User $user): int|false => array_search($user->username, $usernames, true))->values()->toBase();
    }
}
