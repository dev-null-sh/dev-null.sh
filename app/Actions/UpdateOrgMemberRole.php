<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrgEvent;
use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOrgMemberRole
{
    public function __construct(private CreateConversationEvent $createConversationEvent)
    {
        //
    }

    public function handle(User $actor, Conversation $org, User $member, OrgRole $role): void
    {
        DB::transaction(function () use ($actor, $org, $member, $role): void {
            $updated = ConversationParticipant::query()
                ->where('conversation_id', $org->id)
                ->where('user_id', $member->id)
                ->where('role', '!=', $role)
                ->update(['role' => $role]);

            if ($updated > 0) {
                $this->createConversationEvent->handle($actor, $org, $role === OrgRole::Maintainer ? OrgEvent::Promoted : OrgEvent::Demoted, $member);
            }
        });
    }
}
