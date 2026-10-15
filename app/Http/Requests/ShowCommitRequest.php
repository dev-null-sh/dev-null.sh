<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowCommitRequest extends FormRequest
{
    /**
     * Guests only get the previews of approved commits.
     */
    public function authorize(): Response
    {
        $user = $this->user();

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        $isVisible = $user instanceof User
            ? $commit->isVisibleTo($user)
            : $commit->status === CommitStatus::Approved;

        return $isVisible ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
