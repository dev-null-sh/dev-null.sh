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
use Illuminate\Database\Query\Builder;
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

        return collect($commits)->reject(fn (Commit $commit): bool => $authors->contains($commit->author_id))
            ->mapWithKeys(fn (Commit $commit): array => [$commit->id => collect($this->hashtags->handles($commit->body))->first(fn (string $handle): bool => in_array($handle, $handles, true))])
            ->filter()->all();
    }

    /** @return Paginator<int, Commit> */
    private function page(Guild $guild, User $viewer, int $page): Paginator
    {
        $ids = $this->ids($viewer, [$guild->handle])
            ->skip(($page - 1) * 20)
            ->take(21)
            ->all();

        $commits = Commit::query()->whereKey($ids)
            ->visibleTo($viewer)->withoutMutedAuthorsFor($viewer)
            ->withVisibleReplyCount($viewer)->withContinuationId($viewer)->withCardDataFor($viewer)
            ->newestFirst()->get();

        return new Paginator($commits, 20, $page);
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

        return Commit::query()->visibleTo($viewer)->withoutMutedAuthorsFor($viewer)
            ->whereLike('body', '%#%')->newestFirst()->select(['id', 'body'])->toBase()->cursor()
            ->map(fn (stdClass $commit): Fluent => new Fluent(get_object_vars($commit)))
            ->filter(fn (Fluent $commit): bool => array_intersect($handles, $this->hashtags->handles($commit->string('body')->value())) !== [])
            ->map(fn (Fluent $commit): string => $commit->string('id')->value());
    }
}
