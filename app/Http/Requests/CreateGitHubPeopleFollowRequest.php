<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Queries\GitHubPeopleQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateGitHubPeopleFollowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return (resolve(GitHubPeopleQuery::class)->snapshot($user)['status'] ?? null) === 'ready';
    }

    /**
     * @return array<string, array<int, string|In>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        $eligibleIds = resolve(GitHubPeopleQuery::class)->get($user)['users']->pluck('id')->all();

        return [
            'user_ids' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'user_ids.*' => ['bail', 'required', 'uuid', 'distinct', Rule::in($eligibleIds)],
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function people(): Collection
    {
        /** @var list<string> $ids */
        $ids = $this->validated('user_ids');

        return User::query()->whereKey($ids)->get();
    }
}
