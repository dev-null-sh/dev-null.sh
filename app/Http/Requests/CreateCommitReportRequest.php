<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateCommitReportRequest extends FormRequest
{
    /**
     * Only published commits can be reported, so pending and rejected ones look like they do not exist, even to their authors and moderators.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        if ($commit->trashed() || $commit->status !== CommitStatus::Approved || ! $commit->isVisibleTo($user)) {
            return Response::denyAsNotFound();
        }

        return $commit->user_id !== $user->id
            ? Response::allow()
            : Response::deny('You cannot report your own commit.');
    }

    /**
     * @return array{reason: array<int, string|In>, details: array<int, string>}
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(array_keys(CommitReport::REASONS))],
            'details' => ['nullable', 'string', 'max:500'],
        ];
    }
}
