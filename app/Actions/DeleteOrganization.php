<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TopicType;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

final readonly class DeleteOrganization
{
    /**
     * Deletes an organization GitHub no longer has, with its members, and unlinks the commits that mention it, so none
     * links to a page that is gone.
     */
    public function handle(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            DB::table('commit_topics')
                ->where('topic_type', TopicType::Organization->value)
                ->where('topic_id', $organization->id)
                ->delete();

            $organization->members()->detach();
            $organization->delete();
            $organization->profile->delete();
        });
    }
}
