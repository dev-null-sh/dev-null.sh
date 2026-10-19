<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

final class CreateTagMemberRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $tag = $this->tag();

        return $tag->isMaintainedBy($user) || $user->isAdmin()
            ? Response::allow()
            : Response::deny('Only maintainers can add people.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'members' => ['required', 'array', 'min:1', 'max:'.(Tag::MAX_MEMBERS - 1)],
            'members.*' => ['required', 'string', 'distinct', 'exists:users,username'],
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

            $actor = $this->user();
            assert($actor instanceof User);
            $tag = $this->tag();

            foreach ($this->members() as $index => $member) {
                if ($member->id === $actor->id) {
                    $validator->errors()->add("members.{$index}", 'You are already on this hashtag.');

                    continue;
                }

                if ($tag->members()->where('user_id', $member->id)->exists()) {
                    $validator->errors()->add("members.{$index}", "@{$member->username} is already on this hashtag.");

                    continue;
                }

                if (! $actor->isAdmin() && ! $this->inFollowGraph($actor, $member)) {
                    $validator->errors()->add("members.{$index}", "@{$member->username} must follow you or be someone you follow.");
                }
            }

            if ($tag->members()->count() + $this->members()->count() > Tag::MAX_MEMBERS) {
                $validator->errors()->add('members', 'A hashtag holds up to '.Tag::MAX_MEMBERS.' people.');
            }
        }];
    }

    public function tag(): Tag
    {
        $tag = $this->route('tag');
        assert($tag instanceof Tag);

        return $tag;
    }

    /**
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        /** @var list<string> $usernames */
        $usernames = $this->array('members');

        return User::query()
            ->whereIn('username', $usernames)
            ->get()
            ->sortBy(fn (User $user): int|false => array_search($user->username, $usernames, true))
            ->values()
            ->toBase();
    }

    private function inFollowGraph(User $actor, User $member): bool
    {
        return $actor->following()->whereKey($member->id)->exists()
            || $actor->followers()->whereKey($member->id)->exists();
    }
}
