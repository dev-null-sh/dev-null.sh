<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class UpdateModerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->isAdmin();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'commits' => ['required', 'array', 'max:100'],
            'commits.*' => ['required', 'uuid', 'distinct'],
            'versions' => ['sometimes', 'array', 'max:100'],
            'versions.*' => ['required', 'uuid'],
        ];
    }

    public function ensureReviewed(Commit $commit): void
    {
        if ($this->input('versions.'.$commit->id, $commit->id) !== $commit->version()) {
            throw ValidationException::withMessages(['versions' => 'Nothing approved: the author amended a selected commit. Review the new text.']);
        }
    }
}
