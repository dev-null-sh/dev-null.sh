<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCommitStarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit->isVisibleTo($user);
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
