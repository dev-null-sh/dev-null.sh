<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TopicType;
use App\Models\Commit;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

final readonly class DeleteOrganization
{
    public function __construct(private DeleteCommit $deleteCommit)
    {
        //
    }

    /**
     * Deletes an organization GitHub no longer has, with its members and followers, and unlinks the commits that mention
     * it, so none links to a page that is gone. Its own commits are deleted as their posters would, leaving tombstones
     * for the replies under them, and lose their author with its profile.
     */
    public function handle(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            // A commit posted for it meanwhile waits on the profile, and one restored meanwhile on its own row, then
            // fails or stays deleted, rather than slipping past the deletion and losing its author while still live.
            $organization->profile()->lockForUpdate()->first();
            $organization->profile->commits()->withTrashed()->orderBy('id')->lockForUpdate()->get()
                ->reject(fn (Commit $commit): bool => $commit->trashed())
                ->each(fn (Commit $commit): Commit => $this->deleteCommit->handle($commit));
            $organization->profile->commits()->withTrashed()->update(['author_id' => null]);

            DB::table('commit_topics')
                ->where('topic_type', TopicType::Organization->value)
                ->where('topic_id', $organization->id)
                ->delete();

            $organization->members()->detach();
            $organization->followers()->detach();
            $organization->delete();
            $organization->profile->delete();
        });
    }
}
