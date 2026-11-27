<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Enums\Feature;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Services\Features;
use App\Services\Hashtags;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type JoinedGuild array{handle: string, name: string, emoji: string, icon_url: string|null, missed_commits: int, read_from: string}
 */
final readonly class JoinedGuildsQuery
{
    private const int COMMITS_READ = 1000;

    public function __construct(private Features $features, private Hashtags $hashtags)
    {
        //
    }

    /**
     * With apiIcons, icon_url is the signed URL API clients load the icon from, as they send no web session.
     *
     * @return list<JoinedGuild>
     */
    public function get(User $user, bool $apiIcons = false): array
    {
        if (! $this->features->available(Feature::Guilds, $user)) {
            return [];
        }

        /** @var array<string, string> $readFrom */
        $readFrom = $this->preferencesOf($user)->whereNull('muted_at')->pluck('read_from', 'guild_id')->all();

        if ($readFrom === []) {
            return [];
        }

        $commits = $this->commitsSince($user, array_keys($readFrom), min($readFrom));
        $ownCommits = $this->ownCommits($user, array_keys($readFrom));
        $guilds = Guild::query()->whereKey(array_keys($readFrom))->get();
        $ownCommitCounts = $guilds->mapWithKeys(fn (Guild $guild): array => [
            $guild->handle => $ownCommits->filter(fn (array $commit): bool => $this->isPostedIn($guild, $commit))->count(),
        ]);

        return array_values($guilds
            ->map(function (Guild $guild) use ($commits, $readFrom, $user, $apiIcons): array {
                $guildReadFrom = CarbonImmutable::parse($readFrom[$guild->id]);

                return [
                    'handle' => $guild->handle,
                    'name' => $guild->name,
                    'emoji' => $guild->emoji,
                    'icon_url' => $apiIcons ? $guild->apiIconUrl($user) : $guild->iconUrl(),
                    'missed_commits' => $commits->filter(fn (array $commit): bool => $this->isPostedIn($guild, $commit)
                        && $commit['created_at']->greaterThan($guildReadFrom))->count(),
                    'read_from' => $guildReadFrom->toIso8601ZuluString(),
                ];
            })
            ->sortBy([
                ['missed_commits', 'desc'],
                fn (array $first, array $second): int => $ownCommitCounts[$second['handle']] <=> $ownCommitCounts[$first['handle']],
                ['handle', 'asc'],
            ])
            ->all());
    }

    public function readFrom(Guild $guild, User $user): ?string
    {
        $readFrom = $this->preferencesOf($user)->where('guild_id', $guild->id)->value('read_from');

        return is_string($readFrom) ? CarbonImmutable::parse($readFrom)->toIso8601String() : null;
    }

    private function preferencesOf(User $user): Builder
    {
        return DB::table('guild_preferences')->where('user_id', $user->id)->whereNotNull('joined_at')
            ->selectRaw('greatest(joined_at, read_at) as read_from')->addSelect('guild_id');
    }

    /**
     * @param  list<string>  $guildIds
     * @return Collection<int, array{created_at: CarbonInterface, guild_id: string|null, handles: list<string>}>
     */
    private function commitsSince(User $user, array $guildIds, string $readFrom): Collection
    {
        $roots = Commit::query()->topLevel()->visibleTo($user)->withoutMutedAuthorsFor($user)->select('id');

        return Commit::query()->visibleTo($user)->withoutMutedAuthorsFor($user)
            ->where('commits.created_at', '>', $readFrom)
            ->where('commits.user_id', '!=', $user->id)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query->whereIn('commits.guild_id', $guildIds)
                ->orWhere(fn (CommitBuilder $query): CommitBuilder => $query->whereNull('commits.guild_id')->whereLike('commits.body', '%#%')))
            ->joinSub($roots, 'roots', fn (JoinClause $join): JoinClause => $join->on('roots.id', '=', DB::raw('coalesce(commits.root_id, commits.id)')))
            ->orderByDesc('commits.created_at')->orderByDesc('commits.id')
            ->limit(self::COMMITS_READ)
            ->get(['commits.created_at', 'commits.guild_id', 'commits.body'])->toBase()
            ->map(fn (Commit $commit): array => [
                'created_at' => $commit->created_at,
                'guild_id' => $commit->guild_id,
                'handles' => $commit->guild_id === null ? $this->hashtags->handles($commit->body) : [],
            ]);
    }

    /**
     * @param  list<string>  $guildIds
     * @return Collection<int, array{guild_id: string|null, handles: list<string>}>
     */
    private function ownCommits(User $user, array $guildIds): Collection
    {
        return Commit::query()
            ->where('commits.user_id', $user->id)
            ->where('commits.status', CommitStatus::Approved)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query->whereIn('commits.guild_id', $guildIds)
                ->orWhere(fn (CommitBuilder $query): CommitBuilder => $query->whereNull('commits.guild_id')->whereLike('commits.body', '%#%')))
            ->latest('commits.created_at')->orderByDesc('commits.id')
            ->limit(self::COMMITS_READ)
            ->get(['commits.guild_id', 'commits.body'])->toBase()
            ->map(fn (Commit $commit): array => [
                'guild_id' => $commit->guild_id,
                'handles' => $commit->guild_id === null ? $this->hashtags->handles($commit->body) : [],
            ]);
    }

    /** @param array{guild_id: string|null, handles: list<string>, ...} $commit */
    private function isPostedIn(Guild $guild, array $commit): bool
    {
        return $commit['guild_id'] === $guild->id
            || ($commit['guild_id'] === null && in_array($guild->handle, $commit['handles'], true));
    }
}
