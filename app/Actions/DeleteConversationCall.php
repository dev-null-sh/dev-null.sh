<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrgEvent;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\CallQuery;
use Illuminate\Support\Facades\Cache;

final readonly class DeleteConversationCall
{
    public function __construct(private CallQuery $calls, private CreateConversationEvent $events)
    {
        //
    }

    /**
     * Ends the call between the two, whichever of them rang: cancelling it, declining it, giving up once it rang out,
     * or forgetting it once the browsers have connected. Only the ones never answered go in the remote's history, and
     * the callee ending one they didn't pick up goes in as what they chose: declining it, or failing to answer it.
     */
    public function handle(User $user, User $partner, OrgEvent $unanswered): void
    {
        foreach ([[$partner, $user], [$user, $partner]] as [$callee, $caller]) {
            $call = $this->calls->get($callee);

            if ($call?->isFrom($caller) !== true) {
                continue;
            }

            Cache::forget(CreateConversationCall::key($callee));

            if (! $call->isAnswered()) {
                $event = match (true) {
                    $user->isNot($caller) => $unanswered,
                    $call->hasRungOut() => OrgEvent::CallMissed,
                    default => OrgEvent::CallCancelled,
                };
                $this->events->handle($user, Conversation::betweenOrFail($user, $partner), $event);
            }
        }
    }
}
