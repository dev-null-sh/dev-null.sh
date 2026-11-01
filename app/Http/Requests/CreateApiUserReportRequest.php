<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateApiUserReportRequest extends FormRequest
{
    /**
     * As on the web, someone who blocked the reporter answers as if they do not exist, and the one who blocked may
     * still report who they blocked. The app gets a code for both refusals.
     */
    public function authorize(): bool
    {
        $reporter = $this->user();
        assert($reporter instanceof User);

        $user = $this->route('user');
        assert($user instanceof User);

        throw_if($reporter->is($user), fn (): ApiException => new ApiException(ApiErrorCode::OwnProfile, __('You cannot report yourself.')));
        throw_if($user->isBlocking($reporter) && ! $reporter->isBlocking($user), fn (): ApiException => ApiException::userUnavailable());

        return true;
    }

    /**
     * @return array<string, array<int, string|In|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(array_keys(CommitReport::REASONS))],
            'details' => ['nullable', 'string', 'max:500'],
        ];
    }
}
