<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Queries\CommunityStatsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class CommunityStats implements Tool
{
    private const array Metrics = ['members', 'new_members', 'public_authors', 'location', 'bio_language'];

    private int $calls = 0;

    public function description(): string
    {
        return 'Count /dev/null members using fixed public-data metrics. Location and bio language results are profile-text matches, not verified demographics or skills. No people lists or combined filters. At most two calls per reply.';
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

        if (! in_array($metric, ['location', 'bio_language'], true) && $term !== '') {
            return 'Combined filters are not supported. Omit term for member and public-author counts.';
        }

        return json_encode(resolve(CommunityStatsQuery::class)->get($metric, $days, $term), JSON_THROW_ON_ERROR);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'metric' => $schema->string()->enum(self::Metrics)->required(),
            'days' => $schema->integer()->enum([7, 30])->description('Rolling window for new_members or public_authors only. Defaults to 7.'),
            'term' => $schema->string()->max(80)->description('One place name for location, or one language for bio_language: '.implode(', ', array_keys(CommunityStatsQuery::Languages)).'. Omit for other metrics. Never send private details.'),
        ];
    }
}
