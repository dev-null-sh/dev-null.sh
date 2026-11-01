<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Enums\TopicType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use App\Queries\ProfileRecipientsQuery;
use Closure;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateMentionNotifications
{
    public function __construct(private ProfileRecipientsQuery $recipients)
    {
        //
    }

    /**
     * Notifies everyone mentioned in each commit, and every member of the organizations it mentions, but only once the
     * commit is approved, so nobody hears about a mention they cannot see yet. Someone mentioned both ways hears about
     * it once, for themselves. Never for mentioning yourself, or for whoever already hears about it as a reply or a
     * quote. Nobody hears from people they mute or from across a block either. Safe to call more than once.
     */
    public function handle(Commit ...$commits): void
    {
        $commits = Commit::query()
            ->whereKey(array_map(fn (Commit $commit): string => $commit->id, $commits))
            ->where('status', CommitStatus::Approved)
            ->whereNotNull('user_id')
            ->with(['parent', 'quotedCommit'])
            ->get();

        $organizations = DB::table('commit_topics')
            ->whereIn('commit_id', $commits->modelKeys())
            ->where('topic_type', TopicType::Organization)
            ->get(['commit_id', 'topic_id'])
            ->groupBy('commit_id');

        /** @var Collection<string, list<string>> $mentionedByCommit */
        $mentionedByCommit = $commits->mapWithKeys(fn (Commit $commit): array => [$commit->id => [
            ...array_values($commit->mentions ?? []),
            ...$organizations->get($commit->id, new Collection())->pluck('topic_id'),
        ]])->filter();

        if ($mentionedByCommit->isEmpty()) {
            return;
        }

        /** @var Collection<string, Collection<int, object{profile_id: string, user_id: string, organization_id: string|null}>> $recipients */
        $recipients = DB::query()->fromSub($this->recipients->builder(), 'recipients')
            ->whereIn('profile_id', $mentionedByCommit->flatten()
                ->concat($commits->pluck('parent.author_id'))
                ->concat($commits->pluck('quotedCommit.author_id'))
                ->filter()->unique()->values()->all())
            ->get()
            ->groupBy('profile_id');
        /** @var Closure(?string): Collection<int, object{profile_id: string, user_id: string, organization_id: string|null}> $recipientsOf */
        $recipientsOf = fn (?string $profileId): Collection => $recipients->get((string) $profileId, new Collection());

        $authorIds = $commits->pluck('user_id')->unique()->values()->all();
        $silencedAuthors = fn (BelongsToMany $authors): BelongsToMany => $authors->whereKey($authorIds)->without('profile')->select('users.id');
        $users = User::query()
            ->whereKey($recipients->flatten()->pluck('user_id')->unique()->values()->all())
            ->without('profile')
            ->with(['muting' => $silencedAuthors, 'blocking' => $silencedAuthors, 'blockers' => $silencedAuthors])
            ->get(['id'])
            ->keyBy('id');

        $notifications = [];

        foreach ($commits as $commit) {
            $authorId = $commit->user_id;
            assert($authorId !== null);
            $hearsOtherwise = $recipientsOf($commit->parent?->author_id)->concat($recipientsOf($commit->quotedCommit?->author_id))->pluck('user_id');

            $mentioned = collect($mentionedByCommit->get($commit->id, []))
                ->flatMap(fn (string $profileId): Collection => $recipientsOf($profileId))
                ->unique('user_id')
                ->reject(fn (object $recipient): bool => $recipient->user_id === $authorId || $hearsOtherwise->contains($recipient->user_id));

            foreach ($mentioned as $recipient) {
                $user = $users->get($recipient->user_id);

                if (! $user instanceof User || $this->isSilenced($user, $authorId)) {
                    continue;
                }

                $notifications[] = [
                    'id' => Str::uuid7()->toString(),
                    'recipient_id' => $user->id,
                    'actor_id' => $authorId,
                    'organization_id' => $recipient->organization_id,
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
