<?php

declare(strict_types=1);

use App\Actions\UpdateCommit;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

it('shows guests every body an approved commit has had, newest first', function (): void {
    config(['app.admins' => ['taylorotwell']]);
    $this->travelTo(now()->startOfSecond());

    $commit = Commit::factory()->for(User::factory()->create(['username' => 'taylorotwell']))->create(['body' => 'Run `composer tset`']);
    $this->travel(2)->minutes();
    resolve(UpdateCommit::class)->handle($commit, 'Run `composer test`');
    $commit->refresh()->forceFill(['status' => CommitStatus::Approved])->save();

    $this->getJson(route('commit-revision.index', $commit))
        ->assertOk()
        ->assertExactJson(['data' => [
            ['body_segments' => [['type' => 'text', 'text' => 'Run '], ['type' => 'code', 'code' => 'composer test']], 'created_at' => now()->toJSON(), 'mentions' => [], 'is_original' => false, 'review' => null],
            ['body_segments' => [['type' => 'text', 'text' => 'Run '], ['type' => 'code', 'code' => 'composer tset']], 'created_at' => $commit->created_at->toJSON(), 'mentions' => [], 'is_original' => true, 'review' => null],
        ]]);
});

it('hides the history of a commit nobody else may see yet', function (): void {
    $commit = Commit::factory()->pending()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('commit-revision.index', $commit))
        ->assertNotFound();

    $this->actingAs($commit->user)
        ->getJson(route('commit-revision.index', $commit))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    config(['app.admins' => ['admin']]);
    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->getJson(route('commit-revision.index', $commit))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('replaces text that was never published instead of keeping it in the history', function (): void {
    $commit = Commit::factory()->pending()->create(['body' => 'Unreviewed text']);
    resolve(UpdateCommit::class)->handle($commit, 'Second try');
    resolve(UpdateCommit::class)->handle($commit->refresh(), 'Third try');

    expect($commit->revisions()->count())->toBe(0)
        ->and($commit->refresh()->edited_at)->toBeNull();

    $this->actingAs($commit->user)->getJson(route('commit-revision.index', $commit))
        ->assertOk()->assertJsonCount(1, 'data')->assertDontSee('Unreviewed text')->assertDontSee('Second try')
        ->assertJsonPath('data.0.is_original', true)
        ->assertJsonPath('data.0.review', 'pending');
});

it('marks the current version as in review while it awaits moderation', function (): void {
    $commit = Commit::factory()->create(['body' => 'Published text']);
    resolve(UpdateCommit::class)->handle($commit, 'Amended text');

    $this->actingAs($commit->user)->getJson(route('commit-revision.index', $commit))
        ->assertOk()
        ->assertJsonPath('data.0.review', 'pending')
        ->assertJsonPath('data.1.review', null);
});
