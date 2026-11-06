<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\PushAccess;
use App\Enums\RemoteStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Collection;

final readonly class PushAccessQuery
{
    /**
     * A fetched request must be merged before replying, and the sender waits until it is, even after it is dropped,
     * so dropping stays private. While requests are off, a conversation can only start with someone who follows the
     * user.
     */
    public static function of(User $user, User $partner, ?ConversationParticipant $mine, ?ConversationParticipant $theirs, ?bool $follows = null): PushAccess
    {
        return match (true) {
            ! $partner->remotes_enabled => PushAccess::Closed,
            $mine?->status === RemoteStatus::Fetched => PushAccess::Request,
            $theirs instanceof ConversationParticipant && $theirs->status !== RemoteStatus::Open => PushAccess::Waiting,
            ! $theirs instanceof ConversationParticipant && ! config('remotes.requests') && ! $partner->acceptsRemotesFrom($user, $follows) => PushAccess::Restricted,
            default => PushAccess::Open,
        };
    }

    public function get(User $user, User $partner): PushAccess
    {
        $participants = ConversationParticipant::query()
            ->whereIn('conversation_id', Conversation::query()->select('id')->where('pair', Conversation::pairOf($user, $partner)))
            ->get()
            ->keyBy('user_id');

        return self::of($user, $partner, $participants->get($user->id), $participants->get($partner->id));
    }

    /**
     * The access to each partner at once: the participants of every conversation the user shares with one of them,
     * and which of them follow the user, in two queries whatever their number.
     *
     * @param  Collection<int, User>  $partners
     * @return Collection<string, PushAccess>
     */
    public function forMany(User $user, Collection $partners): Collection
    {
        $participants = ConversationParticipant::query()
            ->whereIn('conversation_id', Conversation::query()->select('id')->whereIn('pair', $partners->map(fn (User $partner): string => Conversation::pairOf($user, $partner))))
            ->get();
        $mine = $participants->where('user_id', $user->id)->keyBy('conversation_id');
        $theirs = $participants->where('user_id', '!=', $user->id)->keyBy('user_id');
        $followers = $user->followers()->newPivotQuery()->whereIn('follower_id', $partners->pluck('id'))->pluck('follower_id');

        return $partners->mapWithKeys(function (User $partner) use ($user, $mine, $theirs, $followers): array {
            $partnerParticipant = $theirs->get($partner->id);

            return [$partner->id => self::of(
                $user,
                $partner,
                $partnerParticipant instanceof ConversationParticipant ? $mine->get($partnerParticipant->conversation_id) : null,
                $partnerParticipant,
                $followers->contains($partner->id),
            )];
        });
    }
}
