<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\Feature;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use App\Services\Features;
use App\Services\Hashtags;
use Illuminate\Support\Collection;

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

    /**
     * @param  iterable<array-key, Commit|null>  $commits
     * @return array<string, Guild>
     */
    public function forCommits(iterable $commits, ?User $viewer): array
    {
        return $this->get($this->texts($commits), $viewer);
    }

    /**
     * @param  iterable<array-key, Commit|null>  $commits
     * @return list<string>
     */
    private function texts(iterable $commits): array
    {
        $texts = [];

        foreach ($commits as $commit) {
            if ($commit === null || $commit->trashed()) {
                continue;
            }

            $texts[] = $commit->body;
            $translated = $commit->hasAttribute('translation') ? data_get($commit->getAttribute('translation'), 'translated.body_segments') : null;
            $preview = $commit->hasAttribute('reply_preview') ? $commit->getAttribute('reply_preview') : null;

            if (is_array($translated)) {
                $texts[] = collect($translated)->where('type', 'text')->pluck('text')->implode("\n");
            }

            if ($commit->relationLoaded('quotedCommit')) {
                array_push($texts, ...$this->texts([$commit->quotedCommit]));
            }

            if ($commit->relationLoaded('poll') && $commit->poll instanceof Poll) {
                array_push($texts, ...$commit->poll->options->map(fn (PollOption $option): string => $option->label)->all());
            }

            if (is_array($preview) && $preview['data'] instanceof Collection) {
                array_push($texts, ...$this->texts($preview['data']));
            }
        }

        return $texts;
    }
}
