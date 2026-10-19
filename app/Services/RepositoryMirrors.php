<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommitStatus;
use App\Enums\StarFlavor;
use App\Models\Commit;
use App\Models\RepositoryMirror;
use App\Models\RepositoryMirrorEvent;
use App\Models\User;

/** An outbox written in the same transaction as the activity. No GitHub request is made while someone posts. */
final readonly class RepositoryMirrors
{
    public function reaction(RepositoryMirror $mirror, User $user, Commit $commit, ?string $flavor, string $visibility, bool $removed = false): void
    {
        $this->record($mirror, $user, [
            'type' => $removed ? 'reaction.removed' : 'reaction.saved',
            'reaction' => ['emoji' => $this->emoji($flavor), 'visibility' => $visibility],
            // Other people's post text, DMs and private account fields never leave with a reaction.
            'commit' => ['id' => $commit->id, 'url' => $this->url($commit)],
        ]);
    }

    public function commit(Commit $commit, bool $removed = false): void
    {
        if (! $commit->user instanceof User || (! $removed && $commit->status !== CommitStatus::Approved)) {
            return;
        }

        foreach (RepositoryMirror::query()->where('user_id', $commit->user->id)->where('mirror_commits', true)->orderBy('id')->lockForUpdate()->get() as $mirror) {
            $this->record($mirror, $commit->user, [
                'type' => $removed ? 'commit.removed' : 'commit.saved',
                'commit' => [
                    'id' => $commit->id,
                    'url' => $this->url($commit),
                    ...($removed ? [] : ['body' => $commit->body, 'parent_id' => $commit->parent_id, 'quoted_commit_id' => $commit->quoted_commit_id, 'created_at' => $commit->created_at->toIso8601String()]),
                ],
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function record(RepositoryMirror $mirror, User $user, array $payload): void
    {
        $event = new RepositoryMirrorEvent;
        $event->forceFill([
            'repository_mirror_id' => $mirror->id,
            'payload' => [
                'schema_version' => 1,
                'occurred_at' => now()->toIso8601String(),
                'actor' => ['github_id' => $user->github_id, 'username' => $user->username],
                ...$payload,
            ],
        ])->save();
    }

    private function url(Commit $commit): string
    {
        return route('commit.show', ['user' => $commit->user, 'commit' => $commit]);
    }

    private function emoji(?string $flavor): string
    {
        return match ($flavor) {
            null, StarFlavor::Star->value => '⭐',
            StarFlavor::Ship->value => '🚀', StarFlavor::Fire->value => '🔥',
            StarFlavor::Brain->value => '🧠', StarFlavor::Party->value => '🎉',
            StarFlavor::Eyes->value => '👀', default => $flavor,
        };
    }
}
