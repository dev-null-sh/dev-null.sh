<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use App\Rules\VisibleCommit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A commit from the mobile app, sent as JSON. It may reply to a commit or quote one, as on the web, but not both. It has
 * no images or poll yet.
 */
final class CreateApiCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Blank lines are collapsed before the body is measured, as on the web.
     */
    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'body' => CreateCommitRequest::BODY_RULES,
            'parent_id' => ['bail', 'nullable', 'uuid', new VisibleCommit($user)],
            'quoted_commit_id' => ['bail', 'nullable', 'uuid', 'prohibits:parent_id', new VisibleCommit($user, __('That commit is no longer available to quote.'))],
        ];
    }

    /**
     * A parent that went away since it was validated is a 404, so a reply never turns into a top-level commit.
     */
    public function parent(): ?Commit
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->filled('parent_id')
            ? Commit::query()->visibleTo($user)->findOrFail($this->string('parent_id')->value())
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
