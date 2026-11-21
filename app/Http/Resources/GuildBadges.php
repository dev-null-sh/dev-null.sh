<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Commit;
use App\Models\Guild;
use App\Models\Message;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use App\Queries\ConversationsQuery;
use App\Queries\GuildMentionsQuery;
use App\Services\Hashtags;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * @phpstan-import-type GuildMentions from GuildMentionsQuery
 * @phpstan-import-type ConversationSummary from ConversationsQuery
 */
final readonly class GuildBadges
{
    /**
     * @param  GuildMentions  $badges
     */
    private function __construct(private array $badges)
    {
        //
    }

    /**
     * @param  iterable<array-key, Commit|null>  $commits
     */
    public static function forCommits(iterable $commits, ?User $viewer): self
    {
        return self::lookUp(fn (GuildMentionsQuery $query): array => $query->forCommits($commits, $viewer));
    }

    /**
     * @param  iterable<array-key, Message|null>  $messages
     */
    public static function forMessages(iterable $messages, ?User $viewer): self
    {
        return self::lookUp(fn (GuildMentionsQuery $query): array => $query->forMessages($messages, $viewer));
    }

    /**
     * @param  Collection<int, ConversationSummary>  $conversations
     */
    public static function forConversations(Collection $conversations, ?User $viewer): self
    {
        return self::forMessages($conversations->map(fn (array $conversation): ?Message => $conversation['last_message']), $viewer);
    }

    /**
     * @return array<mixed>|null
     */
    public static function badgedPoll(?Poll $poll, ?User $viewer): ?array
    {
        return $poll instanceof Poll
            ? self::forTexts($poll->options->map(fn (PollOption $option): string => $option->label), $viewer)->onPoll($poll->toArray())
            : null;
    }

    /**
     * @param  array<mixed>|null  $translation
     * @return array<mixed>|null
     */
    public static function badgedTranslation(?array $translation, ?User $viewer): ?array
    {
        return $translation === null ? null : self::forTexts([self::translatedText($translation) ?? ''], $viewer)->onTranslation($translation);
    }

    /**
     * @param  iterable<array-key, string>  $texts
     */
    public static function forTexts(iterable $texts, ?User $viewer): self
    {
        return self::lookUp(fn (GuildMentionsQuery $query): array => $query->get($texts, $viewer));
    }

    /**
     * @return GuildMentions
     */
    public function in(string $text): array
    {
        return array_intersect_key($this->badges, array_flip(resolve(Hashtags::class)->handles($text)));
    }

    /**
     * @param  array<mixed>  $commit
     * @return array<mixed>
     */
    public function onCommit(array $commit): array
    {
        if (! is_string($commit['body'] ?? null)) {
            return $commit;
        }

        $commit['guild_mentions'] = $this->in($commit['body']);

        foreach (['parent', 'quoted_commit'] as $key) {
            if (is_array($commit[$key] ?? null)) {
                $commit[$key] = $this->onCommit($commit[$key]);
            }
        }

        if (is_array($commit['reply_preview'] ?? null) && is_array($commit['reply_preview']['data'] ?? null)) {
            $commit['reply_preview']['data'] = array_map(fn (mixed $reply): mixed => is_array($reply) ? $this->onCommit($reply) : $reply, $commit['reply_preview']['data']);
        }

        if (is_array($commit['poll'] ?? null)) {
            $commit['poll'] = $this->onPoll($commit['poll']);
        }

        if (is_array($commit['translation'] ?? null)) {
            $commit['translation'] = $this->onTranslation($commit['translation']);
        }

        return $commit;
    }

    /**
     * @param  array<mixed>  $poll
     * @return array<mixed>
     */
    public function onPoll(array $poll): array
    {
        if (is_array($poll['options'] ?? null)) {
            $poll['options'] = array_map(fn (mixed $option): mixed => is_array($option) && is_string($option['label'] ?? null)
                ? [...$option, 'guild_mentions' => $this->in($option['label'])]
                : $option, $poll['options']);
        }

        return $poll;
    }

    /**
     * @param  array<mixed>  $translation
     * @return array<mixed>
     */
    public function onTranslation(array $translation): array
    {
        $text = self::translatedText($translation);

        if ($text !== null && is_array($translation['translated'])) {
            $translation['translated']['guild_mentions'] = $this->in($text);
        }

        return $translation;
    }

    /**
     * @param  iterable<array-key, Message>  $messages
     * @return list<array<mixed>>
     */
    public function onMessages(iterable $messages): array
    {
        $shown = [];

        foreach ($messages as $message) {
            $shown[] = $this->onMessage($message);
        }

        return $shown;
    }

    /**
     * @param  Collection<int, ConversationSummary>  $conversations
     * @return list<array<mixed>>
     */
    public function onConversations(Collection $conversations): array
    {
        return array_values($conversations->map(fn (array $conversation): array => [
            ...$conversation,
            'last_message' => $conversation['last_message'] instanceof Message ? $this->onMessage($conversation['last_message']) : null,
        ])->all());
    }

    /**
     * @return array<mixed>
     */
    public function onMessage(Message $message): array
    {
        $shown = [...$message->toArray(), 'guild_mentions' => $this->in($message->body)];

        if (is_array($shown['reply_to'] ?? null) && $message->replyTo instanceof Message) {
            $shown['reply_to']['guild_mentions'] = $this->in($message->replyTo->body);
        }

        return $shown;
    }

    /**
     * @param  array<mixed>  $translation
     */
    private static function translatedText(array $translation): ?string
    {
        return is_array($translation['translated'] ?? null) && is_array($translation['translated']['body_segments'] ?? null)
            ? collect($translation['translated']['body_segments'])->where('type', 'text')->pluck('text')->implode("\n")
            : null;
    }

    /**
     * @param  Closure(GuildMentionsQuery): array<string, Guild>  $lookUp
     */
    private static function lookUp(Closure $lookUp): self
    {
        try {
            $guilds = $lookUp(resolve(GuildMentionsQuery::class));
        } catch (QueryException $queryException) {
            report($queryException);

            $guilds = [];
        }

        /** @var GuildMentions $badges */
        $badges = array_map(fn (Guild $guild): array => GuildBadgeResource::make($guild)->resolve(), $guilds);

        return new self($badges);
    }
}
