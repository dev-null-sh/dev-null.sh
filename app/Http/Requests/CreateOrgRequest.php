<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ConversationKind;
use App\Enums\ProfileType;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\RemoteCandidatesQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

final class CreateOrgRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->remotes_enabled ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string|Exists>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:60'],
            'members' => ['required', 'array', 'min:1', 'max:'.(Conversation::MAX_MEMBERS - 1)],
            'members.*' => ['required', 'string', 'distinct', Rule::exists('profiles', 'handle')->where('type', ProfileType::User->value)],
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

            $reasons = resolve(RemoteCandidatesQuery::class)->reasons($user, $this->members(), new Conversation(['kind' => ConversationKind::Org]), $this->members());

            foreach ($this->members() as $index => $member) {
                if ($reasons[$member->id] !== null) {
                    $validator->errors()->add("members.{$index}", "{$member->mention()} {$reasons[$member->id]}.");
                }
            }
        }];
    }

    /**
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        /** @var list<string> $handles */
        $handles = $this->array('members');

        return User::query()->whereHas('profile', fn (Builder $query): Builder => $query->whereIn('handle', $handles))->get()->sortBy(fn (User $user): int|false => array_search($user->handle, $handles, true))->values()->toBase();
    }
}
