<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TopicType;
use App\Models\Commit;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommitTopics
{
    /**
     * Links the commit to the organizations it mentions, by the lowercase handle written in its body. A reference the
     * commit is already linked by keeps what it was linked to. A handle of digits only is still a string reference.
     *
     * @param  array<string, string>  $organizations
     */
    public function handle(Commit $commit, array $organizations): void
    {
        DB::table('commit_topics')->insertOrIgnore(array_map(fn (int|string $reference, string $id): array => [
            'commit_id' => $commit->id,
            'reference' => (string) $reference,
            'topic_type' => TopicType::Organization->value,
            'topic_id' => $id,
        ], array_keys($organizations), $organizations));
    }
}
