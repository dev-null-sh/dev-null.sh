<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteBroadcastSubscriptionRequest extends FormRequest
{
    /**
     * Subscribers leave whenever they want. The owner stays an owner: ownership and deletion are their own future
     * feature, so for now leaving is a subscriber's thing.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $broadcast = $this->route('broadcast');
        assert($broadcast instanceof Conversation);

        $myRole = $user->remotes_enabled ? $broadcast->activeBroadcastRoleOf($user) : null;

        return match (true) {
            ! $user->remotes_enabled || ! $broadcast->isBroadcast() => Response::denyAsNotFound(),
            ! $myRole instanceof BroadcastRole => Response::denyAsNotFound(),
            $myRole === BroadcastRole::Owner => Response::deny('An owner cannot leave their broadcast.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
