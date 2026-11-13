<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Queries\MentionedOrganizationsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCommitTopics
{
    public function __construct(
        private UpdateMentionedOrganizations $updateMentionedOrganizations,
        private MentionedOrganizationsQuery $mentionedOrganizations,
        private CreateCommitTopics $createCommitTopics,
    ) {
        //
    }

    /**
     * Looks the organizations the commit mentions up, then links the commit to them. The links come from the commit as
     * it is after the lookups, locked, so a commit erased in the meantime gets none. Returns when to try again if the
     * rate limit cut the lookups short.
     */
    public function handle(Commit $commit): ?CarbonImmutable
    {
        $handles = $commit->mentionedNonUsers();

        if ($handles === []) {
            return null;
        }

        $retryAt = $this->updateMentionedOrganizations->handle($handles);

        DB::transaction(function () use ($commit): void {
            $commit = Commit::withTrashed()->lockForUpdate()->find($commit->id);

            if ($commit instanceof Commit) {
                $this->createCommitTopics->handle($commit, $this->mentionedOrganizations->get($commit->body, $commit->mentions ?? []));
            }
        });

        return $retryAt;
    }
}
