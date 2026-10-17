<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrgEvent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOrgName
{
    public function __construct(private CreateConversationEvent $createConversationEvent)
    {
        //
    }

    /**
     * Without a name, the org is shown by its members' names.
     */
    public function handle(User $actor, Conversation $org, ?string $name): void
    {
        DB::transaction(function () use ($actor, $org, $name): void {
            $org->update(['name' => $name]);

            $this->createConversationEvent->handle($actor, $org, OrgEvent::Renamed, body: $name ?? '');
        });
    }
}
