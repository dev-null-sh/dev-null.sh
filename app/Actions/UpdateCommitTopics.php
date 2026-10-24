<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\TopicLabelGenerator;
use App\Ai\Agents\TopicMatcher;
use App\Models\Commit;
use App\Models\Topic;
use App\Queries\CommitBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCommitTopics
{
    public function __construct(private TopicLabelGenerator $generator, private TopicMatcher $matcher)
    {
        //
    }

    /**
     * @param  list<string>  $commitIds
     */
    public function handle(array $commitIds): void
    {
        Cache::lock('commit-topic-assignment', 960)->block(0, function () use ($commitIds): void {
            $commits = Commit::query()->approved()->whereKey($commitIds)->whereNull('parent_id')
                ->where('topic_decision', true)->whereNull('topics_classified_at')->where('body', '!=', '')
                ->orderBy('id')->get(['id', 'body']);

            foreach ($commits->chunk(20) as $batch) {
                $batch = $batch->values();
                $topics = Topic::query()->where('last_applied_at', '>=', now()->subMonthNoOverflow())
                    ->whereHas('commits', fn (CommitBuilder $query): CommitBuilder => $query->approved())
                    ->orderBy('id')->get();
                $bodies = $batch->map(fn (Commit $commit): string => $commit->body)->all();
                $values = $topics->map(fn (Topic $topic): string => $topic->value)->all();
                $matches = $this->matcher->match(
                    collect($bodies)->map(fn (string $body): array => ['body' => $body])->all(),
                    collect($values)->map(fn (string $value): array => ['label' => $value])->all(),
                );
                $unmatched = $batch->filter(fn (Commit $commit, int $index): bool => $matches[$index] === []);
                $generated = $this->generator->generateBatch($unmatched->map(fn (Commit $commit): string => $commit->body)->all());
                $labels = collect($generated)->flatMap(fn (array $values, int $index): array => collect($values)
                    ->map(fn (string $value): array => ['label' => $value, 'body' => $bodies[$index], 'commit' => $index])->all())->values()->all();
                $duplicates = $this->matcher->match(
                    collect($labels)->map(fn (array $label): array => ['label' => $label['label'], 'body' => $label['body']])->all(),
                    collect($labels)->map(fn (array $label): array => ['label' => $label['label'], 'body' => $label['body']])->all(),
                    deduplicate: true,
                );
                $canonical = [];
                $assignments = collect($matches)->map(fn (array $indices): array => collect($indices)
                    ->map(fn (int $index): string => $values[$index])->all())->all();

                foreach ($labels as $index => $label) {
                    $first = collect($duplicates[$index])->first(fn (int $candidate): bool => isset($canonical[$candidate]));
                    $canonical[$first ?? $index] = $labels[$first ?? $index]['label'];
                    $assignments[$label['commit']][] = $canonical[$first ?? $index];
                }

                DB::transaction(function () use ($batch, $assignments): void {
                    $locked = Commit::query()->approved()->whereKey($batch->modelKeys())->whereNull('topics_classified_at')
                        ->orderBy('id')->lockForUpdate()->get(['id'])->keyBy('id');
                    Topic::query()->whereIn(DB::raw('LOWER(value)'), collect($assignments)->flatten()
                        ->map(fn (string $value): string => mb_strtolower($value))->unique()->all())
                        ->orderBy('id')->lockForUpdate()->get(['id']);

                    foreach ($batch as $index => $candidate) {
                        $commit = $locked->get($candidate->id);

                        if (! $commit instanceof Commit) {
                            continue;
                        }

                        foreach (array_unique($assignments[$index]) as $value) {
                            $topic = Topic::query()->whereRaw('LOWER(value) = ?', [mb_strtolower($value)])->lockForUpdate()->first();

                            if ($topic instanceof Topic && $topic->last_applied_at->lt(now()->subMonthNoOverflow())) {
                                $topic->delete();
                                $topic = null;
                            }

                            $topic ??= Topic::query()->create(['value' => $value, 'last_applied_at' => now()]);
                            $commit->topics()->syncWithoutDetaching([$topic->id]);
                            $topic->update(['last_applied_at' => now()]);
                        }

                        $commit->update(['topics_classified_at' => now()]);
                    }
                });
            }
        });
    }
}
