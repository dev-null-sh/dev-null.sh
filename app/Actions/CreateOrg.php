<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ConversationKind;
use App\Enums\OrgEvent;
use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class CreateOrg
{
    public function __construct(private CreateConversationEvent $createConversationEvent)
    {
        //
    }

    /**
     * The creator maintains the org; everyone they add contributes, and sees it from the start.
     *
     * @param  Collection<int, User>  $members
     */
    public function handle(User $maintainer, ?string $name, Collection $members): Conversation
    {
        return DB::transaction(function () use ($maintainer, $name, $members): Conversation {
            $org = Conversation::query()->create(['kind' => ConversationKind::Org, 'name' => $name]);

            ConversationParticipant::query()->create([
                'conversation_id' => $org->id,
                'user_id' => $maintainer->id,
                'role' => OrgRole::Maintainer,
            ]);

            $members->each(fn (User $member) => ConversationParticipant::query()->create([
                'conversation_id' => $org->id,
                'user_id' => $member->id,
                'role' => OrgRole::Contributor,
            ]));

            $this->createConversationEvent->handle($maintainer, $org, OrgEvent::Created, body: $name ?? '');

            return $org;
        });
    }
}
