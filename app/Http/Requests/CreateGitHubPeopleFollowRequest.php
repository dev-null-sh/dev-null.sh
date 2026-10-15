<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Queries\GitHubPeopleQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\ProhibitedIf;

final class CreateGitHubPeopleFollowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|In|ProhibitedIf>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        $discovery = resolve(GitHubPeopleQuery::class)->get($user);
        $ready = $discovery['status'] === 'ready';
        $eligibleIds = $ready ? $discovery['users']->pluck('id')->all() : [];

        return [
            'user_ids' => ['bail', 'required', 'array', 'list', 'min:1', 'max:100', Rule::prohibitedIf(! $ready)],
            'user_ids.*' => ['bail', 'required', 'uuid', 'distinct', Rule::in($eligibleIds)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.prohibited' => __('These discovery results are unavailable. Find your GitHub people again.'),
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
