<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class CreateCommitApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->isAdmin();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['expected_version' => ['sometimes', 'uuid']];
    }

    public function ensureReviewed(Commit $commit): void
    {
        if ($this->input('expected_version', $commit->id) !== $commit->version()) {
            throw ValidationException::withMessages(['body' => 'Not approved: the author amended this commit. Review the new text.']);
        }
    }
}
