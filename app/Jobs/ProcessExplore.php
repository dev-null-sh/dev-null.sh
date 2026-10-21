<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\UpdateExploreClassification;
use App\Ai\Agents\ExploreClassifier;
use App\Models\Commit;
use App\Queries\ExploreContentQuery;
use App\Services\ExploreBatch;
use App\Services\ExploreClassification;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

#[Timeout(900)]
#[Tries(3)]
#[Backoff([60, 600])]
final class ProcessExplore implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(config()->string('explore.queue'));
    }

    public function handle(ExploreContentQuery $content, ExploreBatch $packer, ExploreClassification $decoder, UpdateExploreClassification $persist): void
    {
        if (! config()->boolean('explore.enabled') || blank(config('ai.providers.opencode.key'))) {
            return;
        }

        $ids = DB::transaction(function () use ($content): array {
            $ids = $content->waiting()->lock('for update skip locked')->limit(config()->integer('explore.cohort_size'))->pluck('commit_id')->all();
            DB::table('explore_classification_work')->whereIn('commit_id', $ids)->update(['claimed_at' => now()]);

            return $ids;
        });
        if ($ids === []) {
            return;
        }

        $waiting = $content->eligible()->whereKey($ids)->orderByRaw('array_position(ARRAY['.implode(',', array_fill(0, count($ids), '?')).']::uuid[], commits.id)', $ids)
            ->get(['id', 'body', 'created_at', 'embedding'])->toBase();

        try {
            while ($waiting->isNotEmpty()) {
                $batch = $packer->pack($waiting);
                $started = microtime(true);
                $response = new ExploreClassifier()->prompt($batch['prompt'], model: config()->string('explore.model'));
                $data = $decoder->decode($response->toArray(), $batch['commits'], $batch['catalog']);
                $persist->handle($batch['commits'], $batch['catalog'], $data);
                Log::info('Explore classification completed.', [
                    'commits' => $batch['commits']->count(),
                    'milliseconds' => (int) ((microtime(true) - $started) * 1000),
                    'input_tokens' => $response->usage->inputTokens, 'output_tokens' => $response->usage->outputTokens,
                ]);
                throw_if(count($data['assignments']) !== $batch['commits']->count(), RuntimeException::class, 'Explore classification omitted an input.');

                $completed = $batch['commits']->pluck('id');
                $waiting = $waiting->reject(fn (Commit $commit): bool => $completed->contains($commit->id))->values();
            }
        } catch (Throwable $throwable) {
            Log::warning('Explore classification failed.', ['exception' => $throwable::class]);
            throw new RuntimeException('Explore classification failed.');
        } finally {
            DB::table('explore_classification_work')->whereIn('commit_id', $ids)->whereNull('completed_at')->update(['claimed_at' => null]);
            dispatch(new SummarizeExplore());
        }

        if ($content->waiting()->count() >= config()->integer('explore.cohort_size')) {
            dispatch(new self());
        }
    }
}
