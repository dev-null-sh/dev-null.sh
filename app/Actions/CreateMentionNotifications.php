<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use App\Services\Mentions;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateMentionNotifications
{
    public function __construct(private Mentions $mentions)
    {
        //
    }

    /**
     * Notifies everyone mentioned in each commit, but only once the commit is approved, so nobody hears about a
     * mention they cannot see yet. Never for mentioning yourself, and never the parent's author of a reply, who
     * already hears about it as a reply. Nobody hears from people they mute or from across a block either. Safe to
     * call more than once.
     */
    public function handle(Commit ...$commits): void
    {
        $commits = Commit::query()
            ->whereKey(array_map(fn (Commit $commit): string => $commit->id, $commits))
            ->where('status', CommitStatus::Approved)
            ->with('parent')
            ->get();

        $usernamesByCommit = $commits->mapWithKeys(fn (Commit $commit): array => [
            $commit->id => $this->mentions->usernames($commit->body),
        ])->filter();

        if ($usernamesByCommit->isEmpty()) {
            return;
        }

        $authorIds = $commits->pluck('user_id')->unique()->values()->all();
        $silencedAuthors = fn (BelongsToMany $authors): BelongsToMany => $authors->whereKey($authorIds)->select('users.id');

        $users = User::query()
            ->whereIn(DB::raw('LOWER(username)'), $usernamesByCommit->flatten()->unique()->values()->all())
            ->with(['muting' => $silencedAuthors, 'blocking' => $silencedAuthors, 'blockers' => $silencedAuthors])
            ->get(['id', 'username'])
            ->keyBy(fn (User $user): string => Str::lower($user->username));

        $notifications = [];

        foreach ($commits as $commit) {
            $authorId = $commit->user_id;

            if ($authorId === null) {
                continue;
            }

            foreach ($usernamesByCommit->get($commit->id, []) as $username) {
                $user = $users->get($username);

                if (! $user instanceof User || $user->id === $authorId || $user->id === $commit->parent?->user_id || $this->isSilenced($user, $authorId)) {
                    continue;
                }

                $notifications[] = [
                    'id' => Str::uuid7()->toString(),
                    'recipient_id' => $user->id,
                    'actor_id' => $authorId,
                    'type' => NotificationType::Mentioned->value,
                    'commit_id' => $commit->id,
                    'key' => 'mentioned:'.$commit->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        Notification::query()->insertOrIgnore($notifications);
    }

    /**
     * Whether the mentioned user mutes the author, or either one blocks the other.
     */
    private function isSilenced(User $user, string $authorId): bool
    {
        return $user->muting->concat($user->blocking)->concat($user->blockers)->contains('id', $authorId);
    }
}
