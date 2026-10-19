<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastRole;
use App\Enums\BroadcastVisibility;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateBroadcastRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $broadcast = $this->route('broadcast');
        assert($broadcast instanceof Conversation);

        $myRole = $user->remotes_enabled ? $broadcast->activeBroadcastRoleOf($user) : null;

        return match (true) {
            ! $user->remotes_enabled || ! $broadcast->isBroadcast() || ! $myRole instanceof BroadcastRole => Response::denyAsNotFound(),
            $myRole !== BroadcastRole::Owner => Response::deny('Only the owner can change the broadcast.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:60'],
            'visibility' => ['nullable', new Enum(BroadcastVisibility::class)],
        ];
    }

    public function name(): ?string
    {
        return $this->filled('name') ? $this->string('name')->trim()->value() : null;
    }

    public function visibility(): ?BroadcastVisibility
    {
        return $this->enum('visibility', BroadcastVisibility::class);
    }
}
