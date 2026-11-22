<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrgEvent;
use App\Enums\OrgRole;
use App\Exceptions\OrgNeedsMaintainer;
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

    /**
     * The org is locked first, as messages, reactions and new members lock it. Without it, two maintainers stepping
     * down at the same moment both pass the request's check, and the events they record deadlock on each other's
     * rows. So the last maintainer is checked again here, and the second one to step down is refused.
     *
     * @throws OrgNeedsMaintainer
     */
    public function handle(User $actor, Conversation $org, User $member, OrgRole $role): void
    {
        DB::transaction(function () use ($actor, $org, $member, $role): void {
            Conversation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();

            $otherMaintainers = $org->participants()->where('role', OrgRole::Maintainer)->where('user_id', '!=', $member->id);

            if ($role === OrgRole::Contributor && $otherMaintainers->doesntExist()) {
                throw new OrgNeedsMaintainer;
            }

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
