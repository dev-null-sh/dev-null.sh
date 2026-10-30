<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\Call;
use App\Enums\OrgEvent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final readonly class CreateConversationCall
{
    /**
     * How long a call rings. The offer holds the caller's IP addresses, so it is encrypted and gone soon after.
     */
    public const int SECONDS = 45;

    public function __construct(private CreateConversationEvent $events)
    {
        //
    }

    public static function key(User $callee): string
    {
        return "call:{$callee->id}";
    }

    public function handle(User $caller, User $callee, string $offer): void
    {
        $call = new Call($caller->id, $offer, null, now()->getTimestamp());

        // A few seconds longer than it rings, so the caller giving up still finds it, and the call is logged missed.
        Cache::put(self::key($callee), Crypt::encryptString(json_encode($call->toArray(), JSON_THROW_ON_ERROR)), self::SECONDS + 5);

        $this->events->handle($caller, Conversation::betweenOrFail($caller, $callee), OrgEvent::CallStarted);
    }
}
