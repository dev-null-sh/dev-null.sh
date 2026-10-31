<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\Call;
use App\Enums\OrgEvent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final readonly class UpdateConversationCall
{
    public function __construct(private CreateConversationEvent $events)
    {
        //
    }

    /**
     * Answers the partner's call, which they pick up on their next poll and then forget.
     */
    public function handle(User $callee, User $caller, Call $call, string $answer): void
    {
        Cache::put(CreateConversationCall::key($callee), Crypt::encryptString(json_encode($call->answered($answer)->toArray(), JSON_THROW_ON_ERROR)), CreateConversationCall::SECONDS);

        $this->events->handle($callee, Conversation::betweenOrFail($callee, $caller), OrgEvent::CallAnswered);
    }
}
