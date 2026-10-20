<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateApiCommitPastelRequest extends FormRequest
{
    /**
     * Like on the web, only a user with pastéis mode on may give pastéis, and never to their own commit. A commit they
     * may not see, or whose author is gone, looks like it does not exist.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return match (true) {
            ! $user->pasteis_mode => Response::deny(__('Turn on pastéis mode to give pastéis.')),
            $commit->user_id === $user->id => Response::deny(__('You cannot award your own commit.')),
            $commit->user_id === null, ! $commit->isVisibleTo($user) => Response::denyAsNotFound(),
            default => Response::allow(),
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
