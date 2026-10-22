<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastRole;
use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBroadcastMemberRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $broadcast = $this->route('broadcast');
        assert($broadcast instanceof Conversation);
        $member = $this->route('member');
        assert($member instanceof User);
        $participant = $broadcast->participants()->where('user_id', $member->id)->first();

        $myRole = $user->remotes_enabled ? $broadcast->activeBroadcastRoleOf($user) : null;

        return match (true) {
            ! $user->remotes_enabled || ! $broadcast->isBroadcast() || ! $myRole instanceof BroadcastRole => Response::denyAsNotFound(),
            $myRole !== BroadcastRole::Owner => Response::deny('Only the owner can manage members.'),
            $participant === null => Response::denyAsNotFound(),
            $participant->role === BroadcastRole::Owner => Response::deny('The owner cannot be removed or banned.'),
            ! $this->boolean('banned') && $participant->status !== RemoteStatus::Banned => Response::deny('This member is not banned.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['banned' => ['required', 'boolean']];
    }

    public function status(): RemoteStatus
    {
        return $this->boolean('banned') ? RemoteStatus::Banned : RemoteStatus::Dropped;
    }
}
