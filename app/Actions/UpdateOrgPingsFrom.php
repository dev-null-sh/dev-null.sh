<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrgEvent;
use App\Enums\PingsFrom;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class UpdateOrgPingsFrom
{
    public function __construct(private CreateConversationEvent $createConversationEvent)
    {
        //
    }

    /**
     * Logs the change in the org's history, so those who can no longer ping it can see why. Choosing what is already
     * set logs nothing.
     */
    public function handle(User $actor, Conversation $org, PingsFrom $from): void
    {
        if ($org->pings_from === $from) {
            return;
        }

        DB::transaction(function () use ($actor, $org, $from): void {
            $org->update(['pings_from' => $from]);

            $this->createConversationEvent->handle($actor, $org, OrgEvent::PingsChanged, body: $from->value);
        });
    }
}
