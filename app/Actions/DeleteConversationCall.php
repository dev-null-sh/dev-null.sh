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
     * Ends the call between the two, whichever of them rang: cancelling it, declining it, or forgetting it once the
     * browsers have connected.
     */
    public function handle(User $user, User $partner): void
    {
        foreach ([[$partner, $user], [$user, $partner]] as [$callee, $caller]) {
            $call = $this->calls->get($callee);

            if (($call['caller_id'] ?? null) !== $caller->id) {
                continue;
            }

            Cache::forget(CreateConversationCall::key($callee));

            if ($call['answer'] === null) {
                $event = $user->is($caller) ? OrgEvent::CallCancelled : OrgEvent::CallDeclined;
                $this->events->handle($user, Conversation::betweenOrFail($user, $partner), $event);
            }
        }
    }
}
