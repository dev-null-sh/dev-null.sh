<?php

declare(strict_types=1);

namespace App\Queries;

use App\Actions\CreateTomFriendship;
use App\Contracts\Services\GitHubProvider;
use App\Models\Commit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;

final readonly class CommunityStatsQuery
{
    public const array Languages = [
        'php' => ['php'],
        'javascript' => ['javascript', 'js'],
        'typescript' => ['typescript', 'ts'],
        'python' => ['python'],
        'ruby' => ['ruby'],
        'rust' => ['rust'],
        'go' => ['golang', 'go developer', 'go programmer', 'go engineer', 'go language', 'programming in go'],
        'java' => ['java'],
        'kotlin' => ['kotlin'],
        'swift' => ['swift'],
        'c' => ['c developer', 'c programmer', 'c programming', 'c language'],
        'c++' => ['c++', 'cpp'],
        'c#' => ['c#', 'csharp'],
        'dart' => ['dart'],
        'elixir' => ['elixir'],
        'scala' => ['scala'],
        'lua' => ['lua'],
    ];

    public function __construct(private GitHubProvider $github)
    {
        //
    }

    /** @return array<string, mixed>|null */
    public function get(string $metric, int $days, string $term, ?string $startDate = null, ?string $endDate = null): ?array
    {
        return Cache::remember('community-stats:'.hash('sha256', "{$metric}:{$days}:{$term}:{$startDate}:{$endDate}"), 300, function () use ($metric, $days, $term, $startDate, $endDate): ?array {
            $now = now('UTC')->toImmutable();
            $users = User::query()->whereNotNull('users.onboarded_at')->where('users.github_id', '!=', CreateTomFriendship::GITHUB_ID);
            $population = (clone $users)->count();
            $definition = 'Current onboarded members, excluding Tom.';
            $coverage = 'All eligible members.';
            $since = null;

            if (in_array($metric, ['new_members', 'public_authors', 'commits'], true)) {
                $since = $startDate === null ? ($metric === 'commits' ? null : $now->subDays($days)) : CarbonImmutable::parse($startDate, 'UTC')->startOfDay();
                $period = $startDate === null ? "in the last {$days} days" : "from {$startDate} through {$endDate} (inclusive UTC calendar dates)";
                $definition = $metric === 'new_members'
                    ? "Current onboarded members whose accounts were created {$period}, excluding Tom."
                    : "Distinct current onboarded members with an approved, non-deleted public post or reply {$period}, excluding Tom. Not online users.";

                $activity = $metric === 'new_members' ? $users : Commit::query()->visibleTo(null);
                $activity->where('created_at', '<=', $now);

                if ($since !== null) {
                    $activity->where('created_at', '>=', $since);
                }

                if ($endDate !== null) {
                    $activity->where('created_at', '<', CarbonImmutable::parse($endDate, 'UTC')->addDay()->startOfDay());
                }

                if ($metric === 'public_authors') {
                    $users->whereIn('id', $activity->select('user_id'));
                }

                if ($metric === 'commits') {
                    $count = $activity->whereIn('user_id', (clone $users)->select('id'))->count();
                    $definition = 'Approved, non-deleted public posts and replies submitted by current onboarded members, including organization posts, excluding Tom. '.($startDate === null ? 'All time through the observation time.' : "From {$startDate} through {$endDate} (inclusive UTC calendar dates).");
                }
            }

            if (in_array($metric, ['location', 'bio_language'], true)) {
                $terms = $metric === 'bio_language' ? self::Languages[$term] : match ($term) {
                    'uk', 'united kingdom' => ['uk', 'u.k.', 'united kingdom'],
                    'us', 'usa', 'united states', 'united states of america' => ['us', 'usa', 'u.s.a.', 'united states', 'united states of america'],
                    default => [$term],
                };
                $pattern = '(^|[^[:alnum:]_])('.implode('|', array_map(preg_quote(...), $terms)).')($|[^[:alnum:]_])';
                $column = $metric === 'location' ? 'location' : 'bio';
                $users->whereHas('profile', fn (Builder $query): Builder => $query->whereRaw("{$column} ~* ?", [$pattern]));
                $definition = $metric === 'location'
                    ? 'Members whose public profile location mentions the requested place or its listed aliases. Not nationality or verified residence.'
                    : 'Members whose public bio mentions the requested language or its listed aliases. Not all users of that language or a measure of skill. Go and C require explicit programming phrases.';
                $coverage = 'Exact count of case-insensitive whole-term profile matches, not a community-wide demographic count. Missing, ambiguous and unrecognized profile text is not counted.';
            }

            if ($metric === 'github_organization') {
                try {
                    $ids = $this->github->publicOrganizationMemberIds($term);
                } catch (HttpClientException) {
                    return null;
                }

                if ($ids === null) {
                    return null;
                }

                $users->whereIn('github_id', $ids);
                $definition = 'Current onboarded members who publicly show membership of the requested GitHub organization, excluding Tom. Not private GitHub membership or organization membership on /dev/null.';
                $coverage = 'Complete public GitHub membership list intersected with current eligible members. Private memberships are not counted.';
            }

            if ($metric === 'locations') {
                $rows = (clone $users)->join('profiles', 'profiles.id', '=', 'users.id')
                    ->whereNotNull('profiles.location')->whereRaw("btrim(profiles.location) <> ''")
                    ->selectRaw('lower(btrim(profiles.location)) as location, count(*) as members')
                    ->groupByRaw('lower(btrim(profiles.location))')->orderByDesc('members')->orderBy('location')->limit(10)
                    ->toBase()->get()->map(fn (object $row): Fluent => new Fluent(get_object_vars($row)))->map(fn (Fluent $row): array => [
                        'location' => $row->string('location')->value(),
                        'members' => $row->integer('members'),
                    ])->all();
                $definition = 'Up to ten most common public profile location strings among current onboarded members, excluding Tom. Case and surrounding spaces are normalized; cities and countries are not inferred or combined.';
                $coverage = 'Missing or blank locations are omitted. This is not a verified country or nationality breakdown.';
            }

            return [
                'metric' => $metric,
                'term' => $term,
                'count' => $count ?? $users->count(),
                'population' => $population,
                ...isset($rows) ? ['rows' => $rows] : [],
                'definition' => $definition,
                'coverage' => $coverage,
                'matched_terms' => $terms ?? [],
                'since' => $since?->toIso8601String(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'timezone' => 'UTC',
                'as_of' => $now->toIso8601String(),
                'source' => $metric === 'github_organization'
                    ? 'GitHub public organization membership and current /dev/null members.'
                    : 'Public /dev/null profiles and approved public posts. Profile details reflect the last GitHub sync.',
                'cache_seconds' => 300,
            ];
        });
    }
}
