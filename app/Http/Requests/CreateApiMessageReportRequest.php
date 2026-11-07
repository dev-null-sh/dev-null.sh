<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\CommitReport;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateApiMessageReportRequest extends FormRequest
{
    /**
     * A message the user cannot see, or an org event, looks like it does not exist. The app gets a code for their own.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $message = $this->route('message');
        assert($message instanceof Message);

        if ($message->event !== null || ! $message->isVisibleTo($user)) {
            return Response::denyAsNotFound();
        }

        throw_if($message->user_id === $user->id, fn (): ApiException => new ApiException(ApiErrorCode::OwnMessage, __('You cannot report your own message.')));

        return Response::allow();
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
