<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OrgEvent;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\OrgCallQuery;
use Illuminate\Support\Facades\Cache;

final readonly class CreateOrgCall
{
    /**
     * How long someone stays in a call after their browser last checked in, and the call after its last member did.
     */
    public const int SECONDS = 10;

    /**
     * Every member's browser sends to every other's, so a call holds few enough people for each to keep up.
     */
    public const int MAX_MEMBERS = 8;

    public function __construct(private OrgCallQuery $calls, private CreateConversationEvent $events)
    {
        //
    }

    public static function key(Conversation $org): string
    {
        return "org-call:{$org->id}";
    }

    /**
     * The org whose call the user is in, which also tells everyone else they're still there.
     */
    public static function memberKey(string $userId): string
    {
        return "org-call-member:{$userId}";
    }

    /**
     * What one member last handed another: an offer, or the answer to one.
     */
    public static function signalKey(Conversation $org, string $fromId, string $toId): string
    {
        return "org-call-signal:{$org->id}:{$fromId}:{$toId}";
    }

    /**
     * Forgets every offer and answer the user swapped with these members.
     *
     * @param  array<int|string>  $memberIds
     */
    public static function forgetSignals(Conversation $org, User $user, array $memberIds): void
    {
        Cache::deleteMultiple(collect($memberIds)->flatMap(fn (int|string $id): array => [
            self::signalKey($org, $user->id, (string) $id),
            self::signalKey($org, (string) $id, $user->id),
        ])->all());
    }

    /**
     * Joins the org's call, starting a new one when nobody is in it, which rings everyone else and goes in the org's
     * history. A call that filled up since the request was let through is left as it is, and the user stays out.
     */
    public function handle(User $user, Conversation $org): void
    {
        $isStarting = Cache::lock(self::key($org).':lock', 5)->block(5, function () use ($user, $org): bool {
            $current = $this->calls->get($org);
            $call = $current ?? ['started_at' => now()->getTimestampMs(), 'members' => []];

            if (count($call['members']) >= self::MAX_MEMBERS && ! isset($call['members'][$user->id])) {
                return false;
            }

            // A crashed tab rejoining starts clean, without the offers and answers of its last session.
            self::forgetSignals($org, $user, array_keys($call['members']));

            $call['members'][$user->id] = now()->getTimestampMs();

            Cache::put(self::memberKey($user->id), $org->id, self::SECONDS);
            Cache::put(self::key($org), $call, self::SECONDS);

            return $current === null;
        });

        if ($isStarting) {
            $this->events->handle($user, $org, OrgEvent::CallStarted);
        }
    }
}
