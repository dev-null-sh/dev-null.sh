<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Enums\CommitStatus;
use App\Models\User;
use App\Notifications\NewCommitFromFollowing;
use Illuminate\Support\Facades\Notification;

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

it('notifies followers of commits approved right away', function (): void {
    Notification::fake();
    config(['app.admins' => ['nunomaduro']]);

    $admin = User::factory()->create(['username' => 'nunomaduro']);
    $follower = User::factory()->create();
    $follower->following()->attach($admin);

    resolve(CreateCommit::class)->handle($admin, 'Initial commit');

    Notification::assertSentTo($follower, NewCommitFromFollowing::class);
});

it('does not notify followers of pending commits', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $follower = User::factory()->create();
    $follower->following()->attach($user);

    resolve(CreateCommit::class)->handle($user, 'Initial commit');

    Notification::assertNothingSent();
});
