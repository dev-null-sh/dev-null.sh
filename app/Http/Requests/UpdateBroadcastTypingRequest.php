<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBroadcastTypingRequest extends FormRequest
{
    /**
     * Typing is the start of a post, so it belongs to whoever may post.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $broadcast = $this->route('broadcast');
        assert($broadcast instanceof Conversation);

        $role = $broadcast->activeBroadcastRoleOf($user);

        return $user->remotes_enabled && ($role === BroadcastRole::Owner || $role === BroadcastRole::Publisher)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
