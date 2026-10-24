<?php

declare(strict_types=1);

namespace App\Moderation;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Queries\CommitBuilder;
use App\Services\Urls;

final readonly class DeterministicModerationSignals
{
    public function __construct(private Urls $urls)
    {
        //
    }

    /**
     * @return list<array{code: string, weight: int}>
     */
    public function handle(Commit $commit): array
    {
        $signals = [];

        if ($this->isDuplicate($commit)) {
            $signals[] = $this->signal('duplicate');
        }

        if ($this->isAuthorFlood($commit)) {
            $signals[] = $this->signal('author_flood');
        }

        if ($this->isBurst($commit)) {
            $signals[] = $this->signal('burst');
        }

        if ($this->urls->first($commit->body) !== null) {
            $signals[] = $this->signal('has_url');
        }

        if ($this->isInviteLike($commit->body)) {
            $signals[] = $this->signal('invite_like');
        }

        return $signals;
    }

    public function bodyHash(string $body): string
    {
        $normalized = mb_strtolower(mb_trim((string) preg_replace('/\s+/u', ' ', $body)));

        return hash('sha256', $normalized);
    }

    /**
     * @return array{code: string, weight: int}
     */
    private function signal(string $code): array
    {
        return [
            'code' => $code,
            'weight' => $this->integerConfig("moderation.weights.{$code}", 0),
        ];
    }

    private function isDuplicate(Commit $commit): bool
    {
        // Image-only commits share the empty body's hash, so there is no text to compare.
        if (mb_trim($commit->body) === '') {
            return false;
        }

        $hash = $commit->body_hash ?? $this->bodyHash($commit->body);

        // Short replies like "thanks!" match across users all the time, so another user's text only counts once a
        // moderator has rejected it.
        return Commit::query()
            ->where('body_hash', $hash)
            ->whereKeyNot($commit->id)
            ->where(fn (CommitBuilder $query): CommitBuilder => $query
                ->where('status', CommitStatus::Rejected)
                ->orWhere(fn (CommitBuilder $query): CommitBuilder => $query
                    ->where('user_id', $commit->user_id)
                    ->pending()))
            ->exists();
    }

    private function isAuthorFlood(Commit $commit): bool
    {
        $threshold = $this->integerConfig('moderation.author_flood_threshold', 3);

        return Commit::query()
            ->where('user_id', $commit->user_id)
            ->pending()
            ->count() >= $threshold;
    }

    private function isBurst(Commit $commit): bool
    {
        $count = $this->integerConfig('moderation.burst.count', 5);
        $minutes = $this->integerConfig('moderation.burst.minutes', 10);

        // The window ends when the commit was posted, so a backfill scores the burst as it was at submission. Commits
        // posted after this one in the same second are left out, just as they were not there yet.
        return Commit::query()
            ->where('user_id', $commit->user_id)
            ->where('created_at', '>=', $commit->created_at->copy()->subMinutes($minutes))
            ->whereNot(fn (CommitBuilder $query): CommitBuilder => $query->newerThan($commit))
            ->count() >= $count;
    }

    private function isInviteLike(string $body): bool
    {
        return preg_match(
            '/\b(join\s+(my|our)\s+(discord|telegram|whatsapp)|dm\s+me|send\s+me\s+(a\s+)?dm|click\s+(the\s+)?link|free\s+crypto|whatsapp\.me|t\.me\/)/iu',
            $body,
        ) === 1;
    }

    private function integerConfig(string $key, int $default): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : $default;
    }
}
