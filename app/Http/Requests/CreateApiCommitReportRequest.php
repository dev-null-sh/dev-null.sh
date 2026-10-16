<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ApiErrorCode;
use App\Enums\CommitStatus;
use App\Exceptions\ApiException;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateApiCommitReportRequest extends FormRequest
{
    /**
     * As on the web, only published commits can be reported, so pending and rejected ones look like they do not exist,
     * even to their authors. The app gets a code for both refusals.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        $commit = $this->route('commit');
        assert($commit instanceof Commit);

        throw_if($commit->trashed() || $commit->status !== CommitStatus::Approved || ! $commit->isVisibleTo($user), fn (): ApiException => ApiException::commitUnavailable());
        throw_if($commit->user_id === $user->id, fn (): ApiException => new ApiException(ApiErrorCode::OwnCommit, __('You cannot report your own commit.')));

        return true;
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
