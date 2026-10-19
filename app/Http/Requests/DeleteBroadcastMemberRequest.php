<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteBroadcastMemberRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $broadcast = $this->route('broadcast');
        assert($broadcast instanceof Conversation);
        $member = $this->route('member');
        assert($member instanceof User);
        $role = $broadcast->activeBroadcastRoleOf($member);

        $myRole = $user->remotes_enabled ? $broadcast->activeBroadcastRoleOf($user) : null;

        return match (true) {
            ! $user->remotes_enabled || ! $broadcast->isBroadcast() || ! $myRole instanceof BroadcastRole => Response::denyAsNotFound(),
            $myRole !== BroadcastRole::Owner => Response::deny('Only the owner can manage members.'),
            $role === BroadcastRole::Owner => Response::deny('The owner cannot be removed or banned.'),
            ! $role instanceof BroadcastRole => Response::denyAsNotFound(),
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
