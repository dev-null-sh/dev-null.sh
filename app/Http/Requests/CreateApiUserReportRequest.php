<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateApiUserReportRequest extends FormRequest
{
    /**
     * Anyone but themselves, who get a code for it.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        $reported = $this->route('user');
        assert($reported instanceof User);

        throw_if($user->is($reported), fn (): ApiException => new ApiException(ApiErrorCode::OwnProfile, __('You cannot report yourself.')));

        return true;
    }

    /**
     * Reports share the reasons of commit reports.
     *
     * @return array<string, list<string|In>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(array_keys(CommitReport::REASONS))],
            'details' => ['nullable', 'string', 'max:500'],
        ];
    }
}
