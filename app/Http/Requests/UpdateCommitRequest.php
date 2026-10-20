<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class UpdateCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->id === $this->commit()->user_id;
    }

    /**
     * Images still make text optional, as they did when the commit was made.
     *
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'body' => CreateCommitRequest::bodyRules($this->commit()->images()->exists()),
            'expected_version' => ['sometimes', 'uuid'],
            'images' => ['prohibited'],
            'image_descriptions' => ['prohibited'],
            'poll' => ['prohibited'],
            'parent_id' => ['prohibited'],
            'quoted_commit_id' => ['prohibited'],
        ];
    }

    public function ensureEditable(Commit $commit): void
    {
        if (now()->greaterThanOrEqualTo($commit->created_at->copy()->addMinutes(Commit::EDITABLE_MINUTES))) {
            throw ValidationException::withMessages(['body' => 'The 15-minute amendment window has closed. Your changes were not saved.']);
        }

        if ($commit->amendment_locked_at !== null || $commit->replies()->withTrashed()->exists() || $commit->quotes()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['body' => 'This commit has received a reply or quote and can no longer be amended. Your changes were not saved.']);
        }

        if ($this->string('expected_version')->value() !== $commit->version()) {
            throw ValidationException::withMessages(['body' => 'This commit has changed since you opened the amendment form. Your changes were not saved.']);
        }

        if (! $commit->isEditable()) {
            throw ValidationException::withMessages(['body' => 'This commit can no longer be amended.']);
        }

        if ($commit->revisions()->count() >= Commit::MAX_EDITS) {
            throw ValidationException::withMessages(['body' => 'This commit has been amended '.Commit::MAX_EDITS.' times already.']);
        }
    }

    public function commit(): Commit
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit;
    }

    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }
}
