<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListBroadcastsRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->remotes_enabled ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'before' => ['nullable', 'uuid'],
        ];
    }

    public function term(): string
    {
        return $this->string('q')->trim()->ltrim('@')->lower()->value();
    }

    public function beforeId(): ?string
    {
        return $this->filled('before') ? $this->string('before')->value() : null;
    }
}
