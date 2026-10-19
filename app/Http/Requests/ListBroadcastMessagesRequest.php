<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListBroadcastMessagesRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $broadcast = $this->route('broadcast');
        assert($broadcast instanceof Conversation);

        return $user->remotes_enabled && $broadcast->isBroadcast() && $broadcast->activeBroadcastRoleOf($user) instanceof BroadcastRole
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'after' => ['nullable', 'integer', 'min:0'],
            'before' => ['nullable', 'integer', 'min:1'],
            'reactions_after' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function afterId(): ?int
    {
        return $this->filled('after') ? $this->integer('after') : null;
    }

    public function beforeId(): ?int
    {
        return $this->filled('before') ? $this->integer('before') : null;
    }

    public function reactionsAfter(): ?int
    {
        return $this->filled('reactions_after') ? $this->integer('reactions_after') : null;
    }
}
