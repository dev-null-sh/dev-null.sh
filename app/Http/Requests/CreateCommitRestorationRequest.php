<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CreateCommitRestorationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->id === $this->commit()->user_id;
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $commit = $this->commit();

                if (! $commit->trashed()) {
                    $validator->errors()->add('undo', __('This commit is no longer deleted.'));
                } elseif (! $commit->isRestorable()) {
                    $validator->errors()->add('undo', __('The 30-second undo window has expired.'));
                }
            },
        ];
    }

    private function commit(): Commit
    {
        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return $commit;
    }
}
