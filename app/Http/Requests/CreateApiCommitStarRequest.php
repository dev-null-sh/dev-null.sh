<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateApiCommitStarRequest extends FormRequest
{
    /**
     * Like on the web, the user may star any commit they may see but their own. One they may not see looks like it does
     * not exist.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return match (true) {
            $commit->user_id === $user->id => Response::deny(__('You cannot star your own commit.')),
            $commit->isVisibleTo($user) => Response::allow(),
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
