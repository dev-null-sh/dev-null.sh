<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Services\CloudflareStream;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateUserLiveInputRequest extends FormRequest
{
    public function authorize(CloudflareStream $stream): Response
    {
        return $stream->configured() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'hours' => ['required', 'integer', 'between:1,'.User::MAX_LIVE_HOURS],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if ($user instanceof User && $user->live_input_id === null) {
                    $validator->errors()->add('hours', 'Get your stream key first.');
                }
            },
        ];
    }
}
