<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Queries\RelatedCommitsQuery;

/**
 * @return list<string>
 */
function relatedCommitIds(Commit $commit, ?User $viewer = null): array
{
    return resolve(RelatedCommitsQuery::class)->get($commit, $viewer)->pluck('id')->all();
}

it('lists the nearest top-level commits, nearest first', function (): void {
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $farther = Commit::factory()->create(['embedding' => embeddingAtDistance(0.3)]);
    $nearest = Commit::factory()->create(['embedding' => embeddingAtDistance(0.1)]);

    expect(relatedCommitIds($source))->toBe([$nearest->id, $farther->id]);
});

it('never lists the thread being read', function (): void {
    $root = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $reply = Commit::factory()->for($root, 'parent')->create(['embedding' => embeddingVector(0)]);
    Commit::factory()->for($root, 'parent')->create(['embedding' => embeddingVector(0)]);
    $other = Commit::factory()->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($reply))->toBe([$other->id])
        ->and(relatedCommitIds($root))->toBe([$other->id]);
});

it("uses a reply's own vector as the question", function (): void {
    $root = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $reply = Commit::factory()->for($root, 'parent')->create(['embedding' => embeddingVector(1)]);
    Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $closeToReply = Commit::factory()->create(['embedding' => embeddingVector(1)]);

    expect(relatedCommitIds($reply))->toBe([$closeToReply->id]);
});

it('leaves replies out', function (): void {
    $source = Commit::factory()->create(['embedding' => embeddingVector(0)]);
    $otherRoot = Commit::factory()->create(['embedding' => embeddingVector(1)]);
    Commit::factory()->for($otherRoot, 'parent')->create(['embedding' => embeddingVector(0)]);

    expect(relatedCommitIds($source))->toBeEmpty();
});
