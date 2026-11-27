<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Feature;
use App\Models\Guild;
use App\Models\User;
use App\Services\Features;
use App\Services\Hashtags;

/**
 * @phpstan-type GuildMention array{handle: string, name: string, emoji: string, icon_url: string|null}
 * @phpstan-type GuildMentions array<string, GuildMention>
 */
final readonly class GuildMentionsQuery
{
    public function __construct(private Features $features, private Hashtags $hashtags, private GuildsQuery $guilds)
    {
        //
    }

    /**
     * @param  iterable<array-key, string>  $texts
     * @return array<string, Guild>
     */
    public function get(iterable $texts, ?User $viewer): array
    {
        if (! $viewer instanceof User) {
            return [];
        }

        $handles = array_values(collect($texts)->flatMap($this->hashtags->handles(...))->unique()->all());

        if ($handles === [] || ! $this->features->available(Feature::Guilds, $viewer)) {
            return [];
        }

        return $this->guilds->references($handles)->keyBy(fn (Guild $guild): string => mb_strtolower($guild->handle))->all();
    }
}
