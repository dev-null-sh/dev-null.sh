<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCommitStarRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        return match (true) {
            $commit->user_id === $user->id => Response::deny(),
            $commit->isVisibleTo($user) => Response::allow(),
            default => Response::deny(),
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
