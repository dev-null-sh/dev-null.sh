<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\TopicAssignment;
use App\Dto\TopicAssignments;
use App\Models\Commit;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommitTopics
{
    public function handle(TopicAssignments $assignments): void
    {
        DB::transaction(fn () => $this->persist($assignments));
    }

    private function persist(TopicAssignments $assignments): void
    {
        $commits = Commit::query()->approved()->whereKey($assignments->commitIds())
            ->whereDoesntHave('topics')->orderBy('id')->lockForUpdate()->get();
        $eligible = $assignments->items->filter(fn (TopicAssignment $assignment): bool => $commits->contains($assignment->commit));

        if ($eligible->isEmpty()) {
            return;
        }

        $values = $eligible->map(fn (TopicAssignment $assignment): string => mb_strtolower($assignment->value))->unique()->all();
        Topic::query()->whereIn(DB::raw('LOWER(value)'), $values)->orderBy('id')->lockForUpdate()->get();
        $eligible->each($this->attach(...));
    }

    private function attach(TopicAssignment $assignment): void
    {
        $commit = $assignment->commit;
        $topic = $this->topic($assignment->value);
        $commit->topics()->syncWithoutDetaching([$topic->id]);
        $topic->update(['last_applied_at' => now()]);
    }

    private function topic(string $value): Topic
    {
        $topic = Topic::query()->whereRaw('LOWER(value) = ?', [mb_strtolower($value)])->lockForUpdate()->first();

        if ($topic instanceof Topic && $topic->last_applied_at->lt(now()->subMonthNoOverflow())) {
            $topic->delete();
            $topic = null;
        }

        return $topic ?? Topic::query()->create(['value' => $value, 'last_applied_at' => now()]);
    }
}
