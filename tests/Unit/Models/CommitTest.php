<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\Topic;
use App\Models\User;

test('to array', function (): void {
    $commit = Commit::factory()->create()->refresh();

    expect(array_keys($commit->toArray()))
        ->toBe([
            'id',
            'user_id',
            'title',
            'description',
            'created_at',
            'updated_at',
            'status',
        ]);
});

it('belongs to a user', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();

    expect($commit->user->is($user))->toBeTrue();
});

it('casts the status to an enum', function (): void {
    $commit = Commit::factory()->pending()->create()->refresh();

    expect($commit->status)->toBe(CommitStatus::Pending);
});

it('belongs to many topics', function (): void {
    $topic = Topic::factory()->create();
    $commit = Commit::factory()->hasAttached($topic)->create();

    expect($commit->topics->sole()->is($topic))->toBeTrue();
});

it('has promoted topics', function (): void {
    $promoted = Topic::factory()->hasAttached(Commit::factory()->count(Topic::MIN_COMMITS - 1))->create();
    $rare = Topic::factory()->create();
    $commit = Commit::factory()->hasAttached([$promoted, $rare])->create();

    expect($commit->promotedTopics->pluck('id')->all())->toBe([$promoted->id])
        ->and(array_keys($commit->promotedTopics->sole()->toArray()))->toBe(['id', 'name']);
});

it('only counts approved commits towards promoting a topic', function (): void {
    $topic = Topic::factory()
        ->hasAttached(Commit::factory()->count(Topic::MIN_COMMITS - 1)->pending())
        ->create();
    $commit = Commit::factory()->hasAttached($topic)->create();

    expect($commit->promotedTopics)->toBeEmpty();
});
