<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class CreateUserReportRequest extends FormRequest
{
    /**
     * Someone who blocked the reporter is unavailable to them, so they look like they do not exist. It doesn't go the
     * other way: blocking someone must not cost you the chance to report them, and you still see who you blocked.
     */
    public function authorize(): Response
    {
        $reporter = $this->user();
        assert($reporter instanceof User);

        $user = $this->route('user');
        assert($user instanceof User);

        if ($reporter->is($user)) {
            return Response::deny('You cannot report yourself.');
        }

        return $user->isBlocking($reporter) && ! $reporter->isBlocking($user)
            ? Response::denyAsNotFound()
            : Response::allow();
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
