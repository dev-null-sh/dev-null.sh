<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastRole;
use App\Enums\BroadcastVisibility;
use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\BroadcastsQuery;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateBroadcastSubscriptionRequest extends FormRequest
{
    /**
     * Anyone with remotes on can subscribe to a public broadcast, and an invitee accepts their invitation by
     * subscribing to an invite-only one. A block between them and the owner keeps them out either way.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $broadcast = $this->route('broadcast');
        assert($broadcast instanceof Conversation);

        /** @var User $owner */
        $owner = $broadcast->participants()->where('role', BroadcastRole::Owner)->firstOrFail()->user;

        return match (true) {
            ! $user->remotes_enabled || ! $broadcast->isBroadcast() => Response::denyAsNotFound(),
            $broadcast->participants()->where('user_id', $user->id)->where('status', RemoteStatus::Banned)->exists() => Response::deny('You are banned from this broadcast.'),
            $broadcast->visibility !== BroadcastVisibility::Public && ! resolve(BroadcastsQuery::class)->isInvited($user, $broadcast) => Response::deny('This broadcast is invite-only.'),
            $broadcast->activeBroadcastRoleOf($user) instanceof BroadcastRole => Response::deny('Already subscribed.'),
            $owner->hasBlockWith($user) => Response::deny('A block stands between you and the owner.'),
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
