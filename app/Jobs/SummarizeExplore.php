<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\ExploreSummarizer;
use App\Models\Commit;
use App\Models\ExploreTopic;
use App\Queries\ExploreContentQuery;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

#[Timeout(900)]
#[Tries(3)]
#[Backoff([60, 600])]
final class SummarizeExplore implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('explore');
    }

    public function handle(ExploreContentQuery $content): void
    {
        if (! config()->boolean('explore.enabled') || blank(config('ai.providers.opencode.key'))) {
            return;
        }

        try {
            $topics = ExploreTopic::query()->whereNull('suppressed_at')->whereNotNull('summary_dirty_at')
                ->oldest('summary_dirty_at')->orderBy('id')->limit(config()->integer('explore.summary_size'))->get();
            foreach ($topics as $topic) {
                $sources = $content->members($topic)->where('created_at', '>=', now()->subDays(7))
                    ->orderByDesc('hot_score')->orderByDesc('id')->limit(20)->get(['id', 'body', 'created_at'])->toBase();
                if ($sources->isEmpty()) {
                    $topic->update(['summary' => null, 'summary_generated_at' => null, 'summary_dirty_at' => null]);

                    continue;
                }

                do {
                    $prompt = json_encode(['kind' => $topic->kind, 'title' => $topic->title,
                        'posts' => $sources->map(fn (Commit $commit): array => ['posted_at' => $commit->created_at->toIso8601String(), 'text' => $commit->body])->all(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    if (mb_strlen($prompt, '8bit') + mb_strlen(new ExploreSummarizer()->instructions(), '8bit') + 4096 <= config()->integer('explore.prompt_bytes')) {
                        break;
                    }

                    throw_if($sources->count() === 1, RuntimeException::class, 'Explore summary exceeds the configured prompt budget.');

                    $sources = $sources->take($sources->count() - 1)->values();
                } while (true);

                $started = microtime(true);
                $response = new ExploreSummarizer()->prompt($prompt, model: config()->string('explore.model'));
                Log::info('Explore summary completed.', ['topic_id' => $topic->id,
                    'milliseconds' => (int) ((microtime(true) - $started) * 1000),
                    'input_tokens' => $response->usage->inputTokens, 'output_tokens' => $response->usage->outputTokens]);
                $data = Validator::make($response->toArray(), [
                    'title' => ['required', 'string', 'max:240'], 'summary' => ['present', 'string', 'max:1800'],
                ])->validate();
                DB::transaction(function () use ($topic, $sources, $data, $content): void {
                    $current = ExploreTopic::query()->lockForUpdate()->findOrFail($topic->id);
                    if ($current->suppressed_at !== null || $current->summary_revision !== $topic->summary_revision
                        || $content->eligible()->whereKey($sources->pluck('id'))->lockForUpdate()->get(['id'])->toBase()->count() !== $sources->count()) {
                        return;
                    }

                    $current->update(['title' => $current->kind === 'story' ? $data['title'] : $current->title,
                        'summary' => $data['summary'], 'summary_generated_at' => now(), 'summary_dirty_at' => null]);
                    DB::table('explore_summary_sources')->where('topic_id', $current->id)->delete();
                    DB::table('explore_summary_sources')->insert($sources->map(fn (Commit $commit): array => ['topic_id' => $current->id, 'commit_id' => $commit->id])->all());
                });
            }

            if ($topics->count() === config()->integer('explore.summary_size')) {
                dispatch(new self());
            }
        } catch (Throwable $throwable) {
            Log::warning('Explore summary failed.', ['exception' => $throwable::class]);
            throw new RuntimeException('Explore summary failed.');
        }
    }
}
