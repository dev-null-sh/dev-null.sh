<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use App\Rules\Unclaimed;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCommitRejectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->isAdmin();
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'reason' => ['nullable', 'string', 'max:280'],
            'commit' => [new Unclaimed($user)],
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function validationData(): array
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return [...$this->all(), 'commit' => $commit->id];
    }
}
