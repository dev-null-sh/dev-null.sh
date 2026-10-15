<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use App\Rules\VisibleCommit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'body' => ['required', 'string', 'min:3', 'max:280'],
            'parent_id' => ['nullable', 'uuid', new VisibleCommit($user)],
            'quoted_commit_id' => ['nullable', 'uuid', 'prohibits:parent_id', new VisibleCommit($user, __('That commit is no longer available to quote.'))],
        ];
    }

    public function parent(): ?Commit
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->filled('parent_id')
            ? Commit::query()->visibleTo($user)->find($this->string('parent_id')->value())
            : null;
    }

    public function quotedCommit(): ?Commit
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->filled('quoted_commit_id')
            ? Commit::query()->visibleTo($user)->find($this->string('quoted_commit_id')->value())
            : null;
    }
}
