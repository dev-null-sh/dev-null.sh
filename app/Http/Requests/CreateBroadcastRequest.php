<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastVisibility;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class CreateBroadcastRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->remotes_enabled ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'visibility' => ['required', new Enum(BroadcastVisibility::class)],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->trim()->value();
    }

    public function visibility(): BroadcastVisibility
    {
        return $this->enum('visibility', BroadcastVisibility::class) ?? BroadcastVisibility::Invite;
    }
}
