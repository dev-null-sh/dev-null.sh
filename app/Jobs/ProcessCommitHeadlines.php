<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateCommitHeadlines;
use App\Contracts\Services\HeadlineProvider;
use App\Enums\CommitStatus;
use App\Exceptions\CommitProcessingFailed;
use App\Models\Commit;
use App\Models\Headline;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Backoff([60, 600])]
#[Timeout(900)]
#[Tries(3)]
final class ProcessCommitHeadlines implements ShouldQueue
{
    use Queueable;

    public const int BATCH_SIZE = 20;

    /** @param list<string> $commitIds */
    public function __construct(public readonly array $commitIds)
    {
        //
    }

    public static function dispatchFor(Commit ...$commits): void
    {
        Commit::query()->whereKey(array_map(fn (Commit $commit): string => $commit->id, $commits))
            ->whereNull('parent_id')->where('status', CommitStatus::Approved)->where('body', '!=', '')
            ->whereNull('headlines_classified_at')->whereDoesntHave('headlines')->orderBy('id')->get()
            ->chunk(self::BATCH_SIZE)
            ->each(fn (Collection $batch) => dispatch(new self(array_values($batch->modelKeys())))->afterCommit());
    }

    public function handle(HeadlineProvider $provider): void
    {
        $commits = Commit::query()->whereKey($this->commitIds)->whereNull('parent_id')
            ->where('status', CommitStatus::Approved)->where('body', '!=', '')
            ->whereNull('headlines_classified_at')->whereDoesntHave('headlines')->with('quotedCommit')->orderBy('id')->get()->toBase();

        if ($commits->isEmpty()) {
            return;
        }

        // Keep the shared pool stable through matching and publication, and let pruning wait for new links to commit.
        $lock = Cache::lock(Headline::PROCESSING_LOCK, Headline::PROCESSING_LOCK_SECONDS);
        assert($lock instanceof Lock);
        $lock->block(30);

        try {
            $candidates = $this->filterCandidates($provider, $commits);
            $unsuitable = $commits->except($candidates->keys()->all());
            $this->linkCommits($unsuitable, $unsuitable->map(fn (Commit $commit): array => [])->all(), $lock);

            $matches = $this->matchExistingHeadlines($provider, $candidates);
            $existing = $this->linkCommits($candidates, $matches, $lock);
            $unmatched = $candidates->except(array_keys($matches));
            $generated = $this->linkCommits($unmatched, $this->generateHeadlines($provider, $unmatched), $lock);

            Log::info('Headline batch completed', [
                'commits' => $commits->count(),
                'suitable_candidates' => $candidates->count(),
                'linked_existing' => $existing['linked'],
                'new_headlines' => $existing['new_headlines'] + $generated['new_headlines'],
            ]);
        } catch (Throwable $throwable) {
            throw new CommitProcessingFailed('Headline batch processing', $commits->firstOrFail()->id, $throwable);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  Collection<int, Commit>  $commits
     * @return Collection<int, Commit>
     */
    private function filterCandidates(HeadlineProvider $provider, Collection $commits): Collection
    {
        return $commits->only($provider->candidates($commits->map(fn (Commit $commit): string => $commit->headlineBody())->all()));
    }

    /**
     * @param  Collection<int, Commit>  $commits
     * @return array<int, list<string>>
     */
    private function matchExistingHeadlines(HeadlineProvider $provider, Collection $commits): array
    {
        return $provider->matches($commits->map(fn (Commit $commit): string => $commit->headlineBody())->all(),
            array_values(Headline::query()->orderBy('id')->get()->map(fn (Headline $headline): string => $headline->name)->all()));
    }

    /**
     * @param  Collection<int, Commit>  $commits
     * @return array<int, list<string>>
     */
    private function generateHeadlines(HeadlineProvider $provider, Collection $commits): array
    {
        $generated = $provider->generate($commits->map(fn (Commit $commit): string => $commit->headlineBody())->all());
        $deduplicated = $provider->deduplicate($generated);

        return collect($deduplicated)->map(fn (string $name): array => [$name])->all();
    }

    /**
     * @param  Collection<int, Commit>  $commits
     * @param  array<int, list<string>>  $headlines
     * @return array{linked: int, new_headlines: int}
     */
    private function linkCommits(Collection $commits, array $headlines, Lock $lock): array
    {
        $stats = ['linked' => 0, 'new_headlines' => 0];

        foreach ($commits->only(array_keys($headlines)) as $index => $commit) {
            // Slow synchronous backfills can outlive the lease: never publish after another owner can change the pool.
            throw_unless($lock->refresh(), LockTimeoutException::class);

            // Finish cheap link jobs before the next stage or backfill batch reads the headline pool.
            $created = new LinkCommitHeadlines($commit, $headlines[$index], hash('sha256', $commit->headlineBody()))
                ->handle(resolve(UpdateCommitHeadlines::class));

            if ($created !== null) {
                $stats['linked']++;
                $stats['new_headlines'] += $created;
            }
        }

        return $stats;
    }
}
