<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Guild;
use App\Models\User;
use App\Queries\GuildMentionsQuery;
use App\Services\Hashtags;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Enriches already-authorized response text, resolving its loaded children before one badge lookup for the batch.
 *
 * @phpstan-import-type GuildMentions from GuildMentionsQuery
 */
final class ShownGuildMentions
{
    /**
     * @param  iterable<array-key, string>  $texts
     * @return GuildMentions
     */
    public static function get(iterable $texts, ?User $viewer): array
    {
        try {
            $guilds = resolve(GuildMentionsQuery::class)->get($texts, $viewer);
        } catch (QueryException $queryException) {
            report($queryException);

            return [];
        }

        /** @var GuildMentions */
        return array_map(fn (Guild $guild): array => GuildBadgeResource::make($guild)->resolve(), $guilds);
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $shown
     * @return array<TKey|string, mixed>
     */
    public static function of(array $shown, ?User $viewer): array
    {
        $shown = array_map(self::normalize(...), $shown);
        $mentions = self::get(self::texts($shown), $viewer);

        return self::apply($shown, $mentions);
    }

    /**
     * @param  iterable<array-key, array<mixed>>  $items
     * @return list<array<mixed>>
     */
    public static function many(iterable $items, ?User $viewer): array
    {
        $shown = array_values(collect($items)->map(fn (array $item): array => array_map(self::normalize(...), $item))->all());
        $mentions = self::get(collect($shown)->flatMap(self::texts(...))->all(), $viewer);

        return array_map(fn (array $item): array => self::apply($item, $mentions), $shown);
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof MessageResource || $value instanceof ScheduledCommitResource || $value instanceof ConversationResource || $value instanceof CommitResource || $value instanceof NotificationResource || $value instanceof GuildMentionCollection) {
            $value = clone $value;
            $value->withGuildMentions = false;
        }

        if ($value instanceof JsonResource) {
            $value = $value->resolve();
        } elseif ($value instanceof Collection) {
            return $value->map(self::normalize(...))->all();
        } elseif ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        return is_array($value) ? array_map(self::normalize(...), $value) : $value;
    }

    /**
     * @param  array<array-key, mixed>  $shown
     * @return list<string>
     */
    private static function texts(array $shown): array
    {
        $texts = [];
        $text = self::text($shown);

        if ($text !== null) {
            $texts[] = $text;
        }

        foreach ($shown as $value) {
            if (is_array($value)) {
                array_push($texts, ...self::texts($value));
            }
        }

        return $texts;
    }

    /** @param array<array-key, mixed> $shown */
    private static function text(array $shown): ?string
    {
        if (array_key_exists('body', $shown)) {
            return is_string($shown['body']) ? $shown['body'] : null;
        }

        if (is_string($shown['label'] ?? null) && array_key_exists('votes_count', $shown)) {
            return $shown['label'];
        }

        // Translations only expose segments, never their original cached body or highlighted code as prose.
        return is_array($shown['body_segments'] ?? null)
            ? collect($shown['body_segments'])->where('type', 'text')->pluck('text')->implode("\n")
            : null;
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $shown
     * @param  GuildMentions  $mentions
     * @return array<TKey|string, mixed>
     */
    private static function apply(array $shown, array $mentions): array
    {
        $text = self::text($shown);

        foreach ($shown as $key => $value) {
            if (is_array($value)) {
                $shown[$key] = self::apply($value, $mentions);
            }
        }

        if ($text !== null) {
            $shown['guild_mentions'] = array_intersect_key($mentions, array_flip(resolve(Hashtags::class)->handles($text)));
        }

        return $shown;
    }
}
