<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrgEvent;
use App\Enums\OrgRole;
use App\Exceptions\OrgIsFull;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class CreateOrgMembers
{
    public function __construct(private CreateConversationEvent $createConversationEvent)
    {
        //
    }

    /**
     * New contributors start caught up. Without the history, they only see what is pushed after they joined.
     *
     * The request checks who is in and how many there are, but two maintainers adding people at the same moment both
     * pass that check. So the org is locked, as messages and reactions lock it, and both are checked again here: someone
     * added in the meantime is left as they are, and a batch that no longer fits adds no one.
     *
     * @param  Collection<int, User>  $members
     *
     * @throws OrgIsFull
     */
    public function handle(User $actor, Conversation $org, Collection $members, bool $sharesHistory): void
    {
        DB::transaction(function () use ($actor, $org, $members, $sharesHistory): void {
            $latest = Conversation::query()->whereKey($org->id)->lockForUpdate()->firstOrFail()->last_message_id ?? 0;

            $participantIds = $org->participants()->pluck('user_id');
            $newcomers = $members->reject(fn (User $member): bool => $participantIds->contains($member->id));

            if ($participantIds->count() + $newcomers->count() > Conversation::MAX_MEMBERS) {
                throw new OrgIsFull;
            }

            foreach ($newcomers as $member) {
                ConversationParticipant::query()->create([
                    'conversation_id' => $org->id,
                    'user_id' => $member->id,
                    'role' => OrgRole::Contributor,
                    'visible_from_id' => $sharesHistory ? 0 : $latest,
                    'read_through_id' => $latest,
                    'last_message_at' => now(),
                ]);

                $this->createConversationEvent->handle($actor, $org, OrgEvent::Added, $member);
            }
        });
    }
}
