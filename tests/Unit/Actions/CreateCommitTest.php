<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;

it('may create a commit', function (): void {
    $user = User::factory()->create();

    $action = resolve(CreateCommit::class);

    $commit = $action->handle($user, 'Hello, devnull!');

    expect($commit->exists)->toBeTrue()
        ->and($commit->body)->toBe('Hello, devnull!')
        ->and($commit->user->is($user))->toBeTrue();
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

it('notifies the parent author about replies of admins', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $parent = Commit::factory()->create();

    $action = resolve(CreateCommit::class);

    $reply = $action->handle(User::factory()->create(['username' => 'nunomaduro']), 'Nice one', $parent);

    expect($parent->user->notifications()->sole()->commit_id)->toBe($reply->id);
});

it('does not notify about pending replies', function (): void {
    $parent = Commit::factory()->create();

    $action = resolve(CreateCommit::class);

    $action->handle(User::factory()->create(), 'Nice one', $parent);

    expect(Notification::query()->count())->toBe(0);
});
