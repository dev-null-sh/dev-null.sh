<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Enums\RepliesPerPage;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowCommitPhotoRequest extends FormRequest
{
    /**
     * Guests are sent on to the commit, so they only need it to be approved.
     */
    public function authorize(): Response
    {
        $user = $this->user();

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        if (! $user instanceof User) {
            return $commit->status === CommitStatus::Approved ? Response::allow() : Response::denyAsNotFound();
        }

        $photo = (int) $this->route('photo');

        return $commit->isVisibleTo($user) && $commit->imagesAreVisibleTo($user) && $photo >= 1 && $photo <= $commit->images()->count()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function repliesPerPage(): int
    {
        return RepliesPerPage::of($this)->value;
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
