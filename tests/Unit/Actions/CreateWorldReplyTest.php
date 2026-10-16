<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Actions\CreateWorldReply;
use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\User;

it('answers an approved hello world once', function (): void {
    $commit = Commit::factory()->create(['body' => 'Hello, World!']);

    $action = resolve(CreateWorldReply::class);

    $action->handle($commit);
    $action->handle($commit);

    $reply = $commit->replies()->sole();

    expect($reply->body)->toBe('hello.')
        ->and($reply->status)->toBe(CommitStatus::Approved)
        ->and($reply->user->username)->toBe('world')
        ->and($reply->user->github_id)->toBe('0')
        ->and($commit->user->notifications()->sole()->commit_id)->toBe($reply->id);
});

it('answers when an admin commits hello world', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $commit = resolve(CreateCommit::class)->handle(
        User::factory()->create(['username' => 'nunomaduro']),
        'hello world',
    );

    expect($commit->replies()->sole()->body)->toBe('hello.');
});

it('waits until a hello world is approved', function (): void {
    $commit = resolve(CreateCommit::class)->handle(User::factory()->create(), 'hello world');

    expect($commit->status)->toBe(CommitStatus::Pending)
        ->and($commit->replies()->count())->toBe(0);

    resolve(UpdateCommitStatuses::class)->handle([$commit->id], CommitStatus::Approved);

    $reply = $commit->replies()->sole();

    expect($reply->body)->toBe('hello.')
        ->and($commit->user->notifications()->where('type', NotificationType::Replied)->sole()->commit_id)->toBe($reply->id);
});

it('stays quiet for anything else', function (): void {
    $commit = Commit::factory()->create(['body' => 'hello world, we shipped']);

    resolve(CreateWorldReply::class)->handle($commit);

    expect($commit->replies()->count())->toBe(0)
        ->and(User::query()->where('username', 'world')->exists())->toBeFalse();
});

it('does not answer itself', function (): void {
    $world = User::factory()->create(['github_id' => '0', 'username' => 'world']);
    $commit = Commit::factory()->for($world)->create(['body' => 'hello world']);

    resolve(CreateWorldReply::class)->handle($commit);

    expect($commit->replies()->count())->toBe(0);
});

it('stays quiet when someone already has the name', function (): void {
    User::factory()->create(['username' => 'world']);
    $commit = Commit::factory()->create(['body' => 'hello world']);

    resolve(CreateWorldReply::class)->handle($commit);

    expect($commit->replies()->count())->toBe(0);
});
