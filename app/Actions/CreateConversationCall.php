<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrgEvent;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\CallQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

final readonly class CreateConversationCall
{
    public function __construct(private CreateConversationEvent $events, private CallQuery $calls)
    {
        //
    }

    /**
     * How long a call rings. The offer holds the caller's IP addresses, so it is encrypted and gone soon after.
     */
    public const int SECONDS = 45;

    public static function key(User $callee): string
    {
        return "call:{$callee->id}";
    }

    public function handle(User $caller, User $callee, string $offer): void
    {
        $alreadyRinging = ($this->calls->get($callee)['caller_id'] ?? null) === $caller->id;

        Cache::put(self::key($callee), Crypt::encryptString(json_encode([
            'caller_id' => $caller->id,
            'offer' => $offer,
            'answer' => null,
        ], JSON_THROW_ON_ERROR)), self::SECONDS);

        if (! $alreadyRinging) {
            $this->events->handle($caller, Conversation::betweenOrFail($caller, $callee), OrgEvent::CallStarted);
        }
    }
}
