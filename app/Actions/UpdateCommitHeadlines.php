<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\Headline;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCommitHeadlines
{
    /**
     * @param  list<string>  $names
     * @return int|null Newly created headlines, or null when the result is stale or already applied.
     */
    public function handle(Commit $commit, array $names, string $bodyHash): ?int
    {
        return DB::transaction(function () use ($commit, $names, $bodyHash): ?int {
            $locked = Commit::withTrashed()->whereKey([$commit->id, $commit->quoted_commit_id])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $current = $locked->get($commit->id);

            if ($current instanceof Commit) {
                $current->setRelation('quotedCommit', $locked->get($current->quoted_commit_id ?? ''));
            }

            if (! $current instanceof Commit || $current->trashed() || $current->parent_id !== null || $current->status !== CommitStatus::Approved || $current->body === '' || $current->quoted_commit_id !== $commit->quoted_commit_id || hash('sha256', $current->headlineBody()) !== $bodyHash || $current->headlines_classified_at !== null || $current->headlines()->exists()) {
                return null;
            }

            $created = 0;

            foreach ($names as $name) {
                $headline = Headline::query()->firstOrCreate(['name' => $name]);
                $current->headlines()->syncWithoutDetaching([$headline->id]);
                $created += (int) $headline->wasRecentlyCreated;
            }

            $current->update(['headlines_classified_at' => now()]);

            return $created;
        });
    }
}
