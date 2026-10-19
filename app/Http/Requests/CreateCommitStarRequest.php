<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StarFlavor;
use App\Models\Commit;
use App\Models\User;
use App\Services\EmojiShortcodes;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateCommitStarRequest extends FormRequest
{
    /**
     * Stars tell authors that others liked their commit, so authors may not star their own.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return match (true) {
            $commit->user_id === $user->id => Response::deny('You cannot star your own commit.'),
            $commit->isVisibleTo($user) => Response::allow(),
            default => Response::deny(),
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(EmojiShortcodes $emoji): array
    {
        return [
            'flavor' => ['nullable', 'string', Rule::in([...array_column(StarFlavor::cases(), 'value'), ...$emoji->emoji()])],
            'public' => ['sometimes', 'boolean'],
            'repositories' => ['sometimes', 'array', 'max:10'],
            'repositories.*' => ['required', 'uuid', 'distinct', Rule::exists('repository_mirrors', 'id')->where('user_id', $this->user()?->id)],
        ];
    }

    public function flavor(): ?string
    {
        $flavor = $this->input('flavor');
        assert($flavor === null || is_string($flavor));

        return $flavor;
    }

    /** @return list<Closure(\Illuminate\Validation\Validator): void> */
    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if (! $this->boolean('public', true) && $this->input('repositories', []) === []) {
                $validator->errors()->add('repositories', 'Choose Public or a connected repository.');
            }
        }];
    }
}
