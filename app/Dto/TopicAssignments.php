<?php

declare(strict_types=1);

namespace App\Dto;

use App\Models\Commit;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Collection as Models;
use Illuminate\Support\Collection;

final readonly class TopicAssignments
{
    /** @param Collection<int, TopicAssignment> $items */
    public function __construct(public Collection $items)
    {
        //
    }

    /**
     * @param  Models<int, Commit>  $commits
     * @param  array<int, list<string>>  $labels
     */
    public static function fromLabels(Models $commits, array $labels): self
    {
        return new self($commits->toBase()->flatMap(fn (Commit $commit, int $index): Collection => self::forCommit($commit, $labels[$index]))->values());
    }

    /**
     * @param  Models<int, Commit>  $commits
     * @param  Models<int, Topic>  $topics
     * @param  array<int, list<int>>  $matches
     */
    public static function fromMatches(Models $commits, Models $topics, array $matches): self
    {
        $values = $topics->map(fn (Topic $topic): string => $topic->value);

        return new self($commits->toBase()->flatMap(fn (Commit $commit, int $index): Collection => self::forCommit($commit, array_values($values->only($matches[$index])->all())))->values());
    }

    /** @return list<string> */
    public function commitIds(): array
    {
        return array_values($this->items->map(fn (TopicAssignment $assignment): string => $assignment->commit->id)->unique()->all());
    }

    /** @return list<array{label: string, body: string}> */
    public function descriptors(): array
    {
        return array_values($this->items->map(fn (TopicAssignment $assignment): array => $assignment->descriptor())->all());
    }

    public function merge(self $assignments): self
    {
        return new self($this->items->concat($assignments->items)->values());
    }

    /** @param array<int, list<int>> $matches */
    public function deduplicated(array $matches): self
    {
        $canonical = new Collection;

        return new self($this->items->map(fn (TopicAssignment $assignment, int $index): TopicAssignment => $this->canonicalize($assignment, $index, $matches[$index], $canonical)));
    }

    /**
     * @param  list<string>  $values
     * @return Collection<int, TopicAssignment>
     */
    private static function forCommit(Commit $commit, array $values): Collection
    {
        return collect($values)->map(fn (string $value): TopicAssignment => new TopicAssignment($commit, $value));
    }

    /**
     * @param  list<int>  $matches
     * @param  Collection<int, TopicAssignment>  $canonical
     */
    private function canonicalize(TopicAssignment $assignment, int $index, array $matches, Collection $canonical): TopicAssignment
    {
        $first = $canonical->only($matches)->first();

        if ($first instanceof TopicAssignment) {
            return $assignment->withValue($first->value);
        }

        $canonical->put($index, $assignment);

        return $assignment;
    }
}
