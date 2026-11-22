<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Feature;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Services\Features;
use App\Services\Hashtags;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Illuminate\Support\LazyCollection;
use stdClass;

final readonly class GuildCommitsQuery
{
    public function __construct(private Hashtags $hashtags)
    {
        //
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    public function snapshot(?User $user, Closure $read): mixed
    {
        if (! resolve(Features::class)->available(Feature::Guilds, $user) || DB::transactionLevel() > 0) {
            return $read();
        }

        return DB::transaction(function () use ($read): mixed {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

            return $read();
        });
    }

    /** @return Paginator<int, Commit> */
    public function get(Guild $guild, User $viewer, int $page): Paginator
    {
        return $this->snapshot($viewer, fn (): Paginator => $this->page($guild, $viewer, $page));
    }

    public function pinned(Guild $guild, User $viewer): ?Commit
    {
        return $this->snapshot($viewer, function () use ($guild, $viewer): ?Commit {
            if ($guild->pinned_commit_id === null || $this->rootIds($guild, $viewer, $guild->pinned_commit_id)->isEmpty()) {
                return null;
            }

            return Commit::query()->withVisibleReplyCount($viewer)->withReplyPreviews($viewer)->withCardDataFor($viewer)
                ->find($guild->pinned_commit_id);
        });
    }

    public function forPreference(User $user, bool $muted): Builder
    {
        $hashtags = resolve(Features::class)->available(Feature::Guilds, $user)
            ? $this->hashtagsOf($this->preferencesOf($user)->whereNotNull($muted ? 'muted_at' : 'joined_at')->get())
            : [];

        return DB::query()->selectRaw('jsonb_array_elements_text(?)::uuid', [$this->ids($user, array_keys($hashtags))->values()->toJson()]);
    }

    /**
     * @param  Collection<int, Commit>  $commits
     * @return Collection<int, Commit>
     */
    public function rejectMuted(Collection $commits, ?User $user): Collection
    {
        if (! $user instanceof User || $commits->isEmpty() || ! resolve(Features::class)->available(Feature::Guilds, $user)) {
            return $commits;
        }

        $muted = $this->preferencesOf($user)->whereNotNull('muted_at')->get();
        $hashtags = array_keys($this->hashtagsOf($muted));

        return $muted->isEmpty() ? $commits : $commits->reject(fn (Commit $commit): bool => $commit->guild_id !== null
            ? $muted->contains('id', $commit->guild_id)
            : array_intersect($hashtags, $this->hashtags->handles($commit->body)) !== [])->values();
    }

    /**
     * @param  iterable<array-key, Commit>  $commits
     * @return array<string, string>
     */
    public function joinedFor(iterable $commits, User $user): array
    {
        if (! resolve(Features::class)->available(Feature::Guilds, $user)) {
            return [];
        }

        $hashtags = $this->hashtagsOf($this->preferencesOf($user)->whereNotNull('joined_at')->whereNull('muted_at')->get());
        $following = $user->following();
        $organizations = $user->followedOrganizations();
        $authors = $following->newPivotQuery()->pluck($following->getRelatedPivotKeyName())
            ->merge($organizations->newPivotQuery()->pluck($organizations->getRelatedPivotKeyName()))->push($user->id);

        return collect($commits)->reject(fn (Commit $commit): bool => $commit->guild_id === null && $authors->contains($commit->author_id))
            ->mapWithKeys(fn (Commit $commit): array => [$commit->id => $commit->guild->handle ?? collect($this->hashtags->handles($commit->body))->map(fn (string $hashtag): ?string => $hashtags[$hashtag] ?? null)->filter()->first()])
            ->filter()->all();
    }

    /** @return LazyCollection<int, string> */
    public function rootIds(Guild $guild, User $viewer, ?string $forRoot = null): LazyCollection
    {
        $hashtags = $guild->hashtags();
        $tags = array_map(fn (string $hashtag): string => '#'.str_replace('ς', 'σ', $hashtag), $hashtags);
        $roots = Commit::query()->topLevel()->visibleTo($viewer)->withoutMutedAuthorsFor($viewer)
            ->when($forRoot !== null, fn (CommitBuilder $query): CommitBuilder => $query->whereKey($forRoot))
            ->select(['id', 'created_at']);

        return Commit::query()->visibleTo($viewer)->withoutMutedAuthorsFor($viewer)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query->where('guild_id', $guild->id)
                ->orWhere(fn (CommitBuilder $query): CommitBuilder => $query->whereNull('guild_id')->whereLike('body', '%#%')))
            ->joinSub($roots, 'roots', fn (JoinClause $join): JoinClause => $join->on('roots.id', '=', DB::raw('coalesce(commits.root_id, commits.id)')))
            ->orderByDesc('roots.created_at')->orderByDesc('roots.id')->orderBy('commits.id')
            ->select(['roots.id', 'commits.body', 'commits.guild_id'])->toBase()->cursor()
            ->map(fn (stdClass $commit): Fluent => new Fluent(get_object_vars($commit)))
            ->filter(fn (Fluent $commit): bool => $commit->get('guild_id') !== null
                || ($commit->string('body')->lower()->replace('ς', 'σ')->contains($tags) && array_intersect($hashtags, $this->hashtags->handles($commit->string('body')->value())) !== []))
            ->map(fn (Fluent $commit): string => $commit->string('id')->value())
            ->unique()->values();
    }

    /** @return Paginator<int, Commit> */
    private function page(Guild $guild, User $viewer, int $page): Paginator
    {
        $ids = $this->rootIds($guild, $viewer)->skip(($page - 1) * 30)->take(31)->all();

        $commits = Commit::query()->whereKey($ids)
            ->withVisibleReplyCount($viewer)->withReplyPreviews($viewer)->withCardDataFor($viewer)
            ->newestFirst()->get();

        return new Paginator($commits, 30, $page, ['path' => Paginator::resolveCurrentPath()]);
    }

    /**
     * The guilds the user has preferences for, with a row for each alias, so one read brings every hashtag of them.
     */
    private function preferencesOf(User $user): Builder
    {
        return DB::table('guild_preferences')->join('guilds', 'guilds.id', '=', 'guild_preferences.guild_id')
            ->leftJoin('guild_aliases', 'guild_aliases.guild_id', '=', 'guilds.id')
            ->where('guild_preferences.user_id', $user->id)
            ->select(['guilds.id', 'guilds.handle', 'guild_aliases.handle as alias']);
    }

    /**
     * The handle of the guild each hashtag leads to, by hashtag: the handles themselves, and the aliases.
     *
     * @param  SupportCollection<int, stdClass>  $guilds
     * @return array<string, string>
     */
    private function hashtagsOf(SupportCollection $guilds): array
    {
        /** @var array<string, string> $handles */
        $handles = $guilds->pluck('handle', 'handle')->all();
        /** @var array<string, string> $aliases */
        $aliases = $guilds->whereNotNull('alias')->pluck('handle', 'alias')->all();

        return [...$handles, ...$aliases];
    }

    /**
     * @param  list<string>  $handles
     * @return LazyCollection<int, string>
     */
    private function ids(User $viewer, array $handles): LazyCollection
    {
        if ($handles === []) {
            return new LazyCollection;
        }

        $guildIds = Guild::query()->whereIn('handle', $handles)->pluck('id')->all();

        return Commit::query()->visibleTo($viewer)->withoutMutedAuthorsFor($viewer)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query->whereIn('guild_id', $guildIds)->orWhereLike('body', '%#%'))
            ->newestFirst()->select(['id', 'body', 'guild_id'])->toBase()->cursor()
            ->map(fn (stdClass $commit): Fluent => new Fluent(get_object_vars($commit)))
            ->filter(fn (Fluent $commit): bool => $commit->get('guild_id') !== null
                ? in_array($commit->string('guild_id')->value(), $guildIds, true)
                : array_intersect($handles, $this->hashtags->handles($commit->string('body')->value())) !== [])
            ->map(fn (Fluent $commit): string => $commit->string('id')->value());
    }
}
