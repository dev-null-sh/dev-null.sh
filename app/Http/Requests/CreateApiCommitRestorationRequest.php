<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CreateApiCommitRestorationRequest extends FormRequest
{
    /**
     * Only the author undoes deleting their commit. Anyone else's looks like it does not exist.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->commit()->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
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
                    $validator->errors()->add('undo', 'This commit is no longer deleted.');
                } elseif (! $commit->isRestorable()) {
                    $validator->errors()->add('undo', 'The 30-second undo window has expired.');
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
