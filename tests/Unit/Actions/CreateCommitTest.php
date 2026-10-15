<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Enums\CommitStatus;
use App\Jobs\AssignCommitTopics;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('may create a commit', function (): void {
    $user = User::factory()->create();

    $action = resolve(CreateCommit::class);

    $commit = $action->handle($user, 'Initial commit', 'Hello, devnull!');

    expect($commit->exists)->toBeTrue()
        ->and($commit->title)->toBe('Initial commit')
        ->and($commit->description)->toBe('Hello, devnull!')
        ->and($commit->user->is($user))->toBeTrue();
});

it('may create a commit without a description', function (): void {
    $action = resolve(CreateCommit::class);

    $commit = $action->handle(User::factory()->create(), 'Initial commit');

    expect($commit->title)->toBe('Initial commit')
        ->and($commit->description)->toBeNull();
});

it('creates commits pending review', function (): void {
    $action = resolve(CreateCommit::class);

    $commit = $action->handle(User::factory()->create(), 'Initial commit');

    expect($commit->status)->toBe(CommitStatus::Pending);
});

it('approves commits of admins right away', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $action = resolve(CreateCommit::class);

    $commit = $action->handle(User::factory()->create(['username' => 'nunomaduro']), 'Initial commit');

    expect($commit->status)->toBe(CommitStatus::Approved);
});

it('queues the commit to be filed under topics', function (): void {
    Queue::fake();

    $commit = resolve(CreateCommit::class)->handle(User::factory()->create(), 'Initial commit');

    Queue::assertPushed(AssignCommitTopics::class, fn (AssignCommitTopics $job): bool => $job->commit->is($commit));
});
