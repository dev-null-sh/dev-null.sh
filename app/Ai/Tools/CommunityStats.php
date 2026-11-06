<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Queries\CommunityStatsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class CommunityStats implements Tool
{
    private const array Metrics = ['members', 'new_members', 'public_authors', 'location', 'locations', 'bio_language', 'github_organization', 'commits'];

    private int $calls = 0;

    public function description(): string
    {
        return 'Aggregate /dev/null statistics: member counts, counts for one location or bio language, a locations breakdown, public GitHub organization membership counts, and total approved public commits including replies. Signups and distinct public authors support rolling 7/30-day windows or inclusive UTC calendar dates. Commits default to all time and also support calendar dates. For one day, use the same start_date and end_date. Today in UTC is '.now('UTC')->toDateString().'. Locations and languages are profile-text matches, not verified demographics or skills. No account identities, member rankings, people lists or combined filters. At most two calls per reply.';
    }

    public function handle(Request $request): string
    {
        if (++$this->calls > 2) {
            return 'Statistics budget exhausted. Answer using the evidence already retrieved.';
        }

        $metric = $request->string('metric')->value();
        $days = $request->integer('days', 7);
        $term = $request->string('term')->trim()->lower()->value();

        if (! in_array($metric, self::Metrics, true) || ! in_array($days, [7, 30], true)) {
            return 'Unsupported metric or period. Choose a listed metric and 7 or 30 days.';
        }

        if ($metric === 'location' && (mb_strlen($term) < 2 || mb_strlen($term) > 80)) {
            return 'Provide one location name between 2 and 80 characters.';
        }

        if ($metric === 'bio_language' && ! array_key_exists($term, CommunityStatsQuery::Languages)) {
            return 'Unsupported language. Choose one of: '.implode(', ', array_keys(CommunityStatsQuery::Languages)).'.';
        }

        if ($metric === 'github_organization' && ! preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,37}[a-z0-9])?\z/', $term)) {
            return 'Provide one GitHub organization login, without @ or a URL, up to 39 characters.';
        }

        if ($metric === 'commits' && $request->has('days')) {
            return 'For commits, omit days. Use start_date and end_date for a calendar range, or neither for all time.';
        }

        if (! in_array($metric, ['location', 'bio_language', 'github_organization'], true) && $term !== '') {
            return 'Combined filters are not supported. Omit term for member and public-author counts.';
        }

        $hasRange = $request->hasAny(['start_date', 'end_date']);

        if ($hasRange) {
            if (! in_array($metric, ['new_members', 'public_authors', 'commits'], true) || $request->has('days')) {
                return 'Date ranges are only supported for new_members, public_authors and commits. Omit days when supplying dates.';
            }

            if (Validator::make($request->all(), [
                'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
                'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:'.now('UTC')->toDateString()],
            ])->fails()) {
                return 'Provide both start_date and end_date as valid YYYY-MM-DD dates in order, no later than today in UTC. For one day, use the same date for both.';
            }
        }

        $result = resolve(CommunityStatsQuery::class)->get(
            $metric,
            $days,
            $term,
            $hasRange ? $request->string('start_date')->value() : null,
            $hasRange ? $request->string('end_date')->value() : null,
        );

        return $result === null
            ? 'GitHub organization membership is unavailable or the public membership scan was incomplete. Do not interpret this as zero or use private /dev/null organization membership instead.'
            : json_encode($result, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'metric' => $schema->string()->enum(self::Metrics)->required(),
            'days' => $schema->integer()->enum([7, 30])->description('Rolling window for new_members or public_authors only. Defaults to 7 when dates are omitted. Omit when supplying start_date and end_date.'),
            'start_date' => $schema->string()->description('Inclusive UTC calendar date in YYYY-MM-DD format, for new_members, public_authors or commits only. Requires end_date; omit days. For a single day, use the same date for both.'),
            'end_date' => $schema->string()->description('Inclusive UTC calendar date in YYYY-MM-DD format, on or after start_date and no later than today. Requires start_date; omit days.'),
            'term' => $schema->string()->max(80)->description('One place name for location, one GitHub organization login without @ or URL for github_organization, or one language for bio_language: '.implode(', ', array_keys(CommunityStatsQuery::Languages)).'. Omit for locations (up to ten most common location strings) and other metrics. Never send private details.'),
        ];
    }
}
