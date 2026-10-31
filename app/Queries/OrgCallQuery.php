<?php

declare(strict_types=1);

namespace App\Queries;

use App\Actions\CreateConversationCall;
use App\Actions\CreateOrgCall;
use App\Enums\ConversationKind;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * An org's call only ever lives in the cache, for as long as anyone is in it. Each member's browser talks to every
 * other member's through the relay, after swapping an offer and an answer with each of them through here.
 *
 * @phpstan-type Call array{started_at: int, members: array<string, int>}
 * @phpstan-type Signal array{id: string, type: 'offer'|'answer', sdp: string}
 */
final readonly class OrgCallQuery
{
    /**
     * The org's call with the members still checking in, or null when nobody is.
     *
     * @return Call|null
     */
    public function get(Conversation $org): ?array
    {
        /** @var Call|null $call */
        $call = Cache::get(CreateOrgCall::key($org));

        return $call === null ? null : $this->alive($org, $call);
    }

    /**
     * The call as one of its members sees it: who is in it, and what each of the others last handed them.
     *
     * @param  Call|null  $call
     * @return array{started_at: int, is_ringing: bool, members: list<array{user: User, joined_at: int}>, signals: array<string, Signal>}|null
     */
    public function forMember(User $user, Conversation $org, ?array $call): ?array
    {
        if ($call === null || ! isset($call['members'][$user->id])) {
            return null;
        }

        $others = array_diff(array_map(strval(...), array_keys($call['members'])), [$user->id]);

        return [
            'started_at' => $call['started_at'],
            'is_ringing' => $this->isRinging($call),
            'members' => $this->members($call),
            'signals' => collect($others)
                ->mapWithKeys(function (string $id) use ($org, $user): array {
                    $signal = Cache::get(CreateOrgCall::signalKey($org, $id, $user->id));

                    /** @var array<string, Signal> $signals */
                    $signals = is_string($signal) ? [$id => json_decode(Crypt::decryptString($signal), true, flags: JSON_THROW_ON_ERROR)] : [];

                    return $signals;
                })
                ->all(),
        ];
    }

    /**
     * The calls going on in the user's orgs, so they can join any of them, and hear the ones that just started.
     *
     * @return list<array{org: array{id: string, name: string|null}, started_at: int, is_ringing: bool, is_muted: bool, members: list<array{user: User, joined_at: int}>}>
     */
    public function ongoing(User $user): array
    {
        if (! $user->remotes_enabled) {
            return [];
        }

        $orgs = Conversation::query()
            ->join('conversation_participants as mine', 'mine.conversation_id', '=', 'conversations.id')
            ->where('mine.user_id', $user->id)
            ->where('conversations.kind', ConversationKind::Org)
            ->get(['conversations.id', 'conversations.name', 'mine.muted_until_at']);

        if ($orgs->isEmpty()) {
            return [];
        }

        // One read for every org's call, so only the orgs with one going on cost more.
        $calls = Cache::many($orgs->map(fn (Conversation $org): string => CreateOrgCall::key($org))->all());

        return array_values($orgs
            ->map(function (Conversation $org) use ($calls): ?array {
                /** @var Call|null $call */
                $call = $calls[CreateOrgCall::key($org)] ?? null;
                $call = $call === null ? null : $this->alive($org, $call);

                return $call === null ? null : [
                    'org' => ['id' => $org->id, 'name' => $org->name],
                    'started_at' => $call['started_at'],
                    'is_ringing' => $this->isRinging($call),
                    'is_muted' => $org->muted_until_at?->isFuture() ?? false,
                    'members' => $this->members($call),
                ];
            })
            ->filter()
            ->all());
    }

    /**
     * Whether the user is in any org's call right now.
     */
    public function isInOne(User $user): bool
    {
        return Cache::has(CreateOrgCall::memberKey($user->id));
    }

    /**
     * Whether the user is in the call of an org other than this one.
     */
    public function isInAnother(User $user, Conversation $org): bool
    {
        return $this->isInOne($user) && Cache::get(CreateOrgCall::memberKey($user->id)) !== $org->id;
    }

    /**
     * The call with only the members still checking in, or null when none is.
     *
     * @param  Call  $call
     * @return Call|null
     */
    private function alive(Conversation $org, array $call): ?array
    {
        $keys = array_map(fn (int|string $id): string => CreateOrgCall::memberKey((string) $id), array_keys($call['members']));
        $orgIds = $keys === [] ? [] : Cache::many($keys);

        $members = array_filter(
            $call['members'],
            fn (string $id): bool => ($orgIds[CreateOrgCall::memberKey($id)] ?? null) === $org->id,
            ARRAY_FILTER_USE_KEY,
        );

        return $members === [] ? null : [...$call, 'members' => $members];
    }

    /**
     * @param  Call  $call
     */
    private function isRinging(array $call): bool
    {
        return now()->getTimestampMs() - $call['started_at'] < CreateConversationCall::SECONDS * 1000;
    }

    /**
     * @param  Call  $call
     * @return list<array{user: User, joined_at: int}>
     */
    private function members(array $call): array
    {
        return array_values(User::query()
            ->findMany(array_keys($call['members']))
            ->map(fn (User $member): array => ['user' => $member, 'joined_at' => $call['members'][$member->id]])
            ->sortBy('joined_at')
            ->all());
    }
}
