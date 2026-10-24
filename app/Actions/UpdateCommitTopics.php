<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\TopicLabelGenerator;
use App\Ai\Agents\TopicMatcher;
use App\Dto\TopicAssignments;
use App\Models\Commit;
use App\Models\Topic;
use App\Services\CommitTopicClassifier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class UpdateCommitTopics
{
    public function __construct(
        private CommitTopicClassifier $classifier,
        private TopicLabelGenerator $generator,
        private TopicMatcher $matcher,
        private CreateCommitTopics $createCommitTopics,
    ) {
        //
    }

    public function handle(Commit ...$commits): void
    {
        Cache::lock('commit-topic-assignment', 960)->block(0, fn () => $this->classify(new Collection(array_values($commits))));
    }

    /** @param Collection<int, Commit> $commits */
    private function classify(Collection $commits): void
    {
        $eligible = Commit::query()->approved()->whereKey($commits->modelKeys())
            ->whereDoesntHave('topics')->orderBy('id')->get();
        $decisions = $this->classifier->classify($eligible->mapWithKeys(fn (Commit $commit): array => [$commit->id => $commit->body])->all());
        $interesting = $eligible->filter(fn (Commit $commit): bool => $decisions[$commit->id])->values();

        if ($interesting->isEmpty()) {
            return;
        }

        $existing = $this->matchTopics($interesting);
        $unmatched = $interesting->whereNotIn('id', $existing->commitIds());
        $generated = TopicAssignments::fromLabels($unmatched, $this->generator->generateBatch($unmatched->map(fn (Commit $commit): string => $commit->body)->all()));

        if ($eligible->count() > 1) {
            $generated = $this->deduplicate($generated);
        }

        $this->createCommitTopics->handle($existing->merge($generated));
    }

    /** @param Collection<int, Commit> $commits */
    private function matchTopics(Collection $commits): TopicAssignments
    {
        $topics = Topic::query()->where('last_applied_at', '>=', now()->subMonthNoOverflow())->orderBy('id')->get();
        $matches = $this->matcher->match(
            $commits->map(fn (Commit $commit): array => ['body' => $commit->body])->all(),
            $topics->map(fn (Topic $topic): array => ['label' => $topic->value])->all(),
        );

        return TopicAssignments::fromMatches($commits, $topics, $matches);
    }

    private function deduplicate(TopicAssignments $assignments): TopicAssignments
    {
        $descriptors = $assignments->descriptors();
        $matches = $this->matcher->match($descriptors, $descriptors, deduplicate: true);

        return $assignments->deduplicated($matches);
    }
}
