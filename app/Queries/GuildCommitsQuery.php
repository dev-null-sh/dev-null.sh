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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\Paginator;
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
        /** @var list<string> $handles */
        $handles = resolve(Features::class)->available(Feature::Guilds, $user)
            ? DB::table('guild_preferences')->join('guilds', 'guilds.id', '=', 'guild_preferences.guild_id')
                ->where('guild_preferences.user_id', $user->id)->whereNotNull($muted ? 'muted_at' : 'joined_at')->pluck('handle')->all()
            : [];

        return DB::query()->selectRaw('jsonb_array_elements_text(?)::uuid', [$this->ids($user, $handles)->values()->toJson()]);
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

        /** @var array<string, string> $muted */
        $muted = DB::table('guild_preferences')->join('guilds', 'guilds.id', '=', 'guild_preferences.guild_id')
            ->where('guild_preferences.user_id', $user->id)->whereNotNull('muted_at')->pluck('handle', 'guilds.id')->all();

        return $muted === [] ? $commits : $commits->reject(fn (Commit $commit): bool => $commit->guild_id !== null
            ? array_key_exists($commit->guild_id, $muted)
            : array_intersect($muted, $this->hashtags->handles($commit->body)) !== [])->values();
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

        $handles = DB::table('guild_preferences')->join('guilds', 'guilds.id', '=', 'guild_preferences.guild_id')
            ->where('guild_preferences.user_id', $user->id)->whereNotNull('joined_at')->whereNull('muted_at')->orderBy('handle')->pluck('handle')->all();
        $following = $user->following();
        $organizations = $user->followedOrganizations();
        $authors = $following->newPivotQuery()->pluck($following->getRelatedPivotKeyName())
            ->merge($organizations->newPivotQuery()->pluck($organizations->getRelatedPivotKeyName()))->push($user->id);

        return collect($commits)->reject(fn (Commit $commit): bool => $commit->guild_id === null && $authors->contains($commit->author_id))
            ->mapWithKeys(fn (Commit $commit): array => [$commit->id => $commit->guild->handle ?? collect($this->hashtags->handles($commit->body))->first(fn (string $handle): bool => in_array($handle, $handles, true))])
            ->filter()->all();
    }

    /** @return LazyCollection<int, string> */
    public function rootIds(Guild $guild, User $viewer, ?string $forRoot = null): LazyCollection
    {
        return $this->matches($guild, $viewer, $forRoot)
            ->map(fn (Fluent $commit): string => $commit->string('root_id')->value())
            ->unique()->values();
    }

    /** @return LazyCollection<int, Fluent<array-key, mixed>> */
    private function matches(Guild $guild, User $viewer, ?string $forRoot = null): LazyCollection
    {
        $roots = Commit::query()->topLevel()->visibleTo($viewer)->withoutMutedAuthorsFor($viewer)
            ->when($forRoot !== null, fn (CommitBuilder $query): CommitBuilder => $query->whereKey($forRoot))
            ->select(['id', 'created_at']);

        return Commit::query()->visibleTo($viewer)->withoutMutedAuthorsFor($viewer)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query->where('guild_id', $guild->id)
                ->orWhere(fn (CommitBuilder $query): CommitBuilder => $query->whereNull('guild_id')->whereLike('body', '%#%')))
            ->joinSub($roots, 'roots', fn (JoinClause $join): JoinClause => $join->on('roots.id', '=', DB::raw('coalesce(commits.root_id, commits.id)')))
            ->newestFirst()
            ->select(['commits.id', 'roots.id as root_id', 'commits.body', 'commits.guild_id'])->toBase()->cursor()
            ->map(fn (stdClass $commit): Fluent => new Fluent(get_object_vars($commit)))
            ->filter(fn (Fluent $commit): bool => $commit->get('guild_id') !== null
                || (str_contains(str_replace('ς', 'σ', mb_strtolower($commit->string('body')->value())), '#'.str_replace('ς', 'σ', $guild->handle)) && in_array($guild->handle, $this->hashtags->handles($commit->string('body')->value()), true)));
    }

    /** @return Paginator<int, Commit> */
    private function page(Guild $guild, User $viewer, int $page): Paginator
    {
        $ids = $this->matches($guild, $viewer)->skip(($page - 1) * 30)->take(31)
            ->map(fn (Fluent $commit): string => $commit->string('id')->value())->all();

        $commits = Commit::query()->whereKey($ids)
            ->withVisibleReplyCount($viewer)->withReplyPreviews($viewer)->withCardDataFor($viewer)
            ->with(['parent' => fn (BelongsTo $parent): BelongsTo => $parent
                ->withTrashed()->visibleTo($viewer)->withoutMutedAuthorsFor($viewer)->with('author')])
            ->newestFirst()->get();

        return new Paginator($commits, 30, $page, ['path' => Paginator::resolveCurrentPath()]);
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
