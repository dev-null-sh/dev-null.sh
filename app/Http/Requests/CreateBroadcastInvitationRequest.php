<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastRole;
use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CreateBroadcastInvitationRequest extends FormRequest
{
    /**
     * Only the owner invites, and only people with remotes on who stand in no block with them, who are not already
     * in the broadcast.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $broadcast = $this->route('broadcast');
        assert($broadcast instanceof Conversation);

        $myRole = $user->remotes_enabled ? $broadcast->activeBroadcastRoleOf($user) : null;

        return match (true) {
            ! $user->remotes_enabled || ! $broadcast->isBroadcast() || ! $myRole instanceof BroadcastRole => Response::denyAsNotFound(),
            $myRole !== BroadcastRole::Owner => Response::deny('Only the owner can invite.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, list<string|Rule>>
     */
    public function rules(): array
    {
        return [
            'member' => ['required', 'string', 'exists:users,username'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            assert($user instanceof User);

            $broadcast = $this->route('broadcast');
            assert($broadcast instanceof Conversation);

            $invitee = $this->member();

            if (! $invitee->remotes_enabled) {
                $validator->errors()->add('member', "@{$invitee->username} has remotes off.");

                return;
            }

            if ($user->hasBlockWith($invitee)) {
                $validator->errors()->add('member', "@{$invitee->username} cannot be invited across a block.");

                return;
            }

            $status = $broadcast->participants()->where('user_id', $invitee->id)->first()?->status;

            if ($status === RemoteStatus::Banned) {
                $validator->errors()->add('member', "Unban @{$invitee->username} before inviting them.");
            } elseif ($status === RemoteStatus::Open) {
                $validator->errors()->add('member', "@{$invitee->username} is already in the broadcast.");
            }
        }];
    }

    public function member(): User
    {
        $member = User::query()->where('username', $this->string('member')->value())->first();

        assert($member instanceof User);

        return $member;
    }
}
