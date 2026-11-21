<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EmailActivity;
use App\Enums\EmailSignup;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowEmailFormRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->isAdmin() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'activity' => ['nullable', Rule::enum(EmailActivity::class)],
            'signup' => ['nullable', Rule::enum(EmailSignup::class)],
        ];
    }

    public function activity(): EmailActivity
    {
        return $this->enum('activity', EmailActivity::class) ?? EmailActivity::Anyone;
    }

    public function signup(): EmailSignup
    {
        return $this->enum('signup', EmailSignup::class) ?? EmailSignup::Anytime;
    }
}
