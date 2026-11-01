<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TopicType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\ScheduledCommit;
use Illuminate\Support\Facades\DB;

final readonly class DeleteOrganization
{
    public function __construct(private DeleteCommit $deleteCommit, private DeleteStoredFiles $deleteStoredFiles)
    {
        //
    }

    /**
     * Deletes an organization GitHub no longer has, with its members and followers and what they heard about it, and
     * unlinks the commits that mention it, so none links to a page that is gone. Its own commits are deleted as their
     * posters would, leaving tombstones for the replies under them, and lose their author with its profile. What its
     * members scheduled as it goes too, images included.
     */
    public function handle(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            // Locked before the profile, in the order a scheduled commit being posted takes them: it holds its row, then
            // waits on the profile, so taking them the other way round would deadlock.
            $scheduled = ScheduledCommit::query()->where('author_id', $organization->id);
            $scheduled->clone()->orderBy('id')->lockForUpdate()->pluck('id');

            // A commit posted for it meanwhile waits on the profile, and one restored meanwhile on its own row, then
            // fails or stays deleted, rather than slipping past the deletion and losing its author while still live.
            $organization->profile()->lockForUpdate()->first();
            $organization->profile->commits()->withTrashed()->orderBy('id')->lockForUpdate()->get()
                ->reject(fn (Commit $commit): bool => $commit->trashed())
                ->each(fn (Commit $commit): Commit => $this->deleteCommit->handle($commit));
            $organization->profile->commits()->withTrashed()->update(['author_id' => null]);

            $this->deleteStoredFiles->retain($scheduled->clone()->whereNotNull('images')->get(['images'])
                ->flatMap(fn (ScheduledCommit $scheduled): array => array_column($scheduled->images ?? [], 'path'))->all());
            $scheduled->delete();

            DB::table('commit_topics')
                ->where('topic_type', TopicType::Organization->value)
                ->where('topic_id', $organization->id)
                ->delete();

            Notification::query()->where('organization_id', $organization->id)->delete();
            $organization->members()->detach();
            $organization->followers()->detach();
            $organization->delete();
            $organization->profile->delete();
        });
    }
}
