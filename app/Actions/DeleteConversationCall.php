<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Queries\CallQuery;
use Illuminate\Support\Facades\Cache;

final readonly class DeleteConversationCall
{
    public function __construct(private CallQuery $calls)
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
            if (($this->calls->get($callee)['caller_id'] ?? null) === $caller->id) {
                Cache::forget(CreateConversationCall::key($callee));
            }
        }
    }
}
