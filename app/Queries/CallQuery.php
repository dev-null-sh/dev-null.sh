<?php

declare(strict_types=1);

namespace App\Queries;

use App\Actions\CreateConversationCall;
use App\Dto\Call;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Calls only ever live in the cache, for as long as one rings. The browsers hand each other their offer and answer
 * through it, then talk directly, so nothing of the call itself reaches the server.
 */
final readonly class CallQuery
{
    public function get(User $callee): ?Call
    {
        $encrypted = Cache::get(CreateConversationCall::key($callee));

        if (! is_string($encrypted)) {
            return null;
        }

        /** @var array<mixed> $attributes */
        $attributes = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);

        return Call::fromArray($attributes);
    }

    /**
     * The call ringing for the user, until they answer it.
     *
     * @return array{partner: User, sdp: string, is_muted: bool}|null
     */
    public function incoming(User $user): ?array
    {
        $call = $this->get($user);
        $caller = ! $call instanceof Call || $call->isAnswered() ? null : User::query()->find($call->callerId);

        if (! $caller instanceof User || ! $user->canMessage($caller)) {
            return null;
        }

        $mutedUntil = Conversation::between($user, $caller)?->participants()->where('user_id', $user->id)->value('muted_until_at');

        return [
            'partner' => $caller,
            'sdp' => $call->offer,
            'is_muted' => $mutedUntil !== null && now()->lessThan($mutedUntil),
        ];
    }

    /**
     * The user's call to the partner while it rings, with the partner's answer once they accept.
     *
     * @return array{answer: string|null}|null
     */
    public function outgoing(User $user, User $partner): ?array
    {
        $call = $this->get($partner);

        return $call?->isFrom($user) === true ? ['answer' => $call->answer] : null;
    }
}
