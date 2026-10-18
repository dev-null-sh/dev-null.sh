<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowUserRequest extends FormRequest
{
    /**
     * Stars are private, so another person's Stars tab looks like it does not exist.
     */
    public function authorize(): Response
    {
        if ($this->tab() !== 'stars') {
            return Response::allow();
        }

        $user = $this->route('user');
        assert($user instanceof User);

        return $this->user()?->is($user) === true ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', 'in:commits,replies,stars'],
        ];
    }

    /**
     * @return 'commits'|'replies'|'stars'
     */
    public function tab(): string
    {
        return match ($this->input('tab')) {
            'replies' => 'replies',
            'stars' => 'stars',
            default => 'commits',
        };
    }
}
