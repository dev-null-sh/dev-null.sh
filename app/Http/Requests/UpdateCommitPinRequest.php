<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCommitPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit->author_id === $user->id && $commit->parent_id === null;
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
