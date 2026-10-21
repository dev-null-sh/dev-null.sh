<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit->user_id === $user->id;
    }

    /** @return array<string, list<string|ValidationRule>> */
    public function rules(): array
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return ['body' => CreateCommitRequest::bodyRules($commit->images()->exists())];
    }

    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }
}
