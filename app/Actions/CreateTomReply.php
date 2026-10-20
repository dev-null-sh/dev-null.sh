<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\Tom;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class CreateTomReply
{
    public function __construct(private CreateCommit $createCommit)
    {
        //
    }

    public function handle(string $commitId): void
    {
        $commit = Commit::query()->visibleTo(null)->with('user')->find($commitId);
        $tom = User::query()->where('github_id', CreateTomFriendship::GITHUB_ID)->where('username', CreateTomFriendship::USERNAME)->first();

        if (! $commit instanceof Commit || ! $tom instanceof User) {
            return;
        }

        if (! $this->replyable($commit, $tom) || $commit->user?->is($tom) || ! $this->isAddressedToTom($commit, $tom)) {
            return;
        }

        $body = mb_trim(new Tom($commit)->prompt($commit->body)->text);

        if ($body === '') {
            throw new RuntimeException('Tom returned an empty reply.');
        }

        DB::transaction(function () use ($commit, $tom, $body): void {
            $current = Commit::query()->lockForUpdate()->find($commit->id);

            if (! $current instanceof Commit || $current->status !== CommitStatus::Approved
                || $current->body !== $commit->body || ! $this->replyable($current, $tom)) {
                return;
            }

            $this->createCommit->handle($tom, Str::limit($body, Commit::MAX_BODY_LENGTH - 3), parent: $current, linkPreview: false);
        });
    }

    private function isAddressedToTom(Commit $commit, User $tom): bool
    {
        return ($commit->mentions[CreateTomFriendship::USERNAME] ?? null) === $tom->id
            || $commit->parent?->user_id === $tom->id;
    }

    /**
     * Tom replies once per post, and never to someone he has a block with.
     */
    private function replyable(Commit $commit, User $tom): bool
    {
        return $commit->user instanceof User
            && ! $commit->user->hasBlockWith($tom)
            && ! $commit->replies()->withTrashed()->whereBelongsTo($tom)->exists();
    }
}
