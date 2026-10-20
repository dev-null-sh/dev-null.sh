<?php

declare(strict_types=1);

namespace App\Queries;

use App\Actions\CreateTomFriendship;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

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

    /** @return array<string, mixed> */
    public function get(string $metric, int $days, string $term): array
    {
        return Cache::remember('community-stats:'.hash('sha256', "{$metric}:{$days}:{$term}"), 300, function () use ($metric, $days, $term): array {
            $now = now()->toImmutable();
            $users = User::query()->whereNotNull('onboarded_at')->where('github_id', '!=', CreateTomFriendship::GITHUB_ID);
            $population = (clone $users)->count();
            $definition = 'Current onboarded members, excluding Tom.';
            $coverage = 'All eligible members.';
            $since = null;

            if (in_array($metric, ['new_members', 'public_authors'], true)) {
                $since = $now->subDays($days);
                $definition = $metric === 'new_members'
                    ? "Current onboarded members whose accounts were created in the last {$days} days, excluding Tom."
                    : "Distinct current onboarded members with an approved, non-deleted public post or reply in the last {$days} days, excluding Tom. Not online users.";

                if ($metric === 'new_members') {
                    $users->whereBetween('created_at', [$since, $now]);
                } else {
                    $users->whereIn('id', Commit::query()->visibleTo(null)->whereBetween('created_at', [$since, $now])->select('user_id'));
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
                $users->whereRaw("{$column} ~* ?", [$pattern]);
                $definition = $metric === 'location'
                    ? 'Members whose public profile location mentions the requested place or its listed aliases. Not nationality or verified residence.'
                    : 'Members whose public bio mentions the requested language or its listed aliases. Not all users of that language or a measure of skill. Go and C require explicit programming phrases.';
                $coverage = 'Exact count of case-insensitive whole-term profile matches, not a community-wide demographic count. Missing, ambiguous and unrecognized profile text is not counted.';
            }

            return [
                'metric' => $metric,
                'term' => $term,
                'count' => $users->count(),
                'population' => $population,
                'definition' => $definition,
                'coverage' => $coverage,
                'matched_terms' => $terms ?? [],
                'since' => $since?->toIso8601String(),
                'as_of' => $now->toIso8601String(),
                'source' => 'Public /dev/null profiles and approved public posts. Profile details reflect the last GitHub sync.',
                'cache_seconds' => 300,
            ];
        });
    }
}
