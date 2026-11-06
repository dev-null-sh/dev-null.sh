<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateCommitRecommitRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        if (! $commit->isVisibleTo($user)) {
            return Response::denyAsNotFound();
        }

        return $commit->status === CommitStatus::Approved && ! $commit->isOwnedBy($user)
            ? Response::allow()
            : Response::deny();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
