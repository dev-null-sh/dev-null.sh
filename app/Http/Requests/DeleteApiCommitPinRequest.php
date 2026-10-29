<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteApiCommitPinRequest extends FormRequest
{
    /**
     * Only the author pins one of their own top-level commits to their profile, as on the web.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return match (true) {
            $commit->user_id === $user->id && $commit->parent_id === null => Response::allow(),
            $commit->isVisibleTo($user) => Response::deny(),
            default => Response::denyAsNotFound(),
        };
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
