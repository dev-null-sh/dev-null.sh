<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListBroadcastMembersRequest extends FormRequest
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
            $myRole !== BroadcastRole::Owner => Response::deny('Only the owner can manage members.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100'], 'after' => ['nullable', 'integer', 'min:0']];
    }

    public function term(): string
    {
        return $this->string('q')->trim()->lower()->ltrim('@')->value();
    }

    public function afterId(): int
    {
        return $this->integer('after');
    }
}
