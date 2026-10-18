<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Actions\CreateTomFriendship;
use App\Actions\CreateTomReply;
use App\Actions\UpdateCommitStatuses;
use App\Ai\Agents\Tom;
use App\Enums\CommitStatus;
use App\Jobs\EmbedCommit;
use App\Jobs\ModerateCommit;
use App\Jobs\ReplyAsTom;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('creates one reply through the normal moderation pipeline and never replaces a deleted reply', function (): void {
    config(['services.openai.key' => 'test']);
    Queue::fake([ModerateCommit::class, EmbedCommit::class, ReplyAsTom::class]);
    Tom::fake(['A developer community, with rusty friends. :-D'])->preventStrayPrompts();
    $tom = User::factory()->create(['username' => 'tom', 'github_id' => CreateTomFriendship::GITHUB_ID]);
    $root = Commit::factory()->create();
    $commit = Commit::factory()->for($root, 'parent')->create(['body' => '@tom what is this place?', 'mentions' => ['tom' => $tom->id]]);

    resolve(CreateTomReply::class)->handle($commit->id);
    resolve(CreateTomReply::class)->handle($commit->id);

    $reply = $commit->replies()->sole();
    expect($reply->body)->toBe('A developer community, with rusty friends. :-D')
        ->and($reply->user_id)->toBe($tom->id)
        ->and($reply->root_id)->toBe($root->id)
        ->and($reply->status)->toBe(CommitStatus::Pending);
    Queue::assertPushed(ModerateCommit::class, fn (ModerateCommit $job): bool => $job->commit->is($reply));
    Queue::assertPushed(EmbedCommit::class, fn (EmbedCommit $job): bool => $job->commit->is($reply));
    Queue::assertNotPushed(ReplyAsTom::class);
    Tom::assertPrompted('@tom what is this place?');

    $reply->delete();
    resolve(CreateTomReply::class)->handle($commit->id);
    expect($commit->replies()->withTrashed()->count())->toBe(1);
});

it('queues a mention only after approval and ignores code mentions', function (): void {
    config(['ai.providers.opencode.key' => 'test']);
    Queue::fake([ReplyAsTom::class]);
    User::factory()->create(['username' => 'tom', 'github_id' => CreateTomFriendship::GITHUB_ID]);
    $user = User::factory()->create();
    $commit = resolve(CreateCommit::class)->handle($user, '@ToM how did this start?');
    $code = resolve(CreateCommit::class)->handle($user, 'This is code: `@tom`');

    Queue::assertNotPushed(ReplyAsTom::class);
    resolve(UpdateCommitStatuses::class)->handle([$commit->id, $code->id], CommitStatus::Approved);
    resolve(UpdateCommitStatuses::class)->handle([$commit->id], CommitStatus::Approved);

    Queue::assertPushed(ReplyAsTom::class, 1);
    Queue::assertPushed(ReplyAsTom::class, fn (ReplyAsTom $job): bool => $job->commitId === $commit->id && $job->afterCommit === true);
});

it('does not prompt for ineligible mentions', function (string $case): void {
    Tom::fake()->preventStrayPrompts();
    $tom = User::factory()->create(['username' => 'tom', 'github_id' => CreateTomFriendship::GITHUB_ID]);
    $commit = Commit::factory()->create(['body' => '@tom hello', 'mentions' => ['tom' => $tom->id]]);

    match ($case) {
        'pending' => $commit->update(['status' => CommitStatus::Pending]),
        'rejected' => $commit->update(['status' => CommitStatus::Rejected]),
        'deleted' => $commit->delete(),
        'author gone' => $commit->update(['user_id' => null]),
        'self' => $commit->update(['user_id' => $tom->id]),
        'unmentioned' => $commit->update(['mentions' => []]),
        'wrong identity' => $tom->update(['github_id' => '12345']),
        'renamed' => $tom->update(['username' => 'not-tom']),
        'blocked' => $commit->user->blocking()->attach($tom),
        'blocker' => $tom->blocking()->attach($commit->user),
    };

    resolve(CreateTomReply::class)->handle($commit->id);

    Tom::assertNeverPrompted();
    expect($commit->replies()->count())->toBe(0);
})->with(['pending', 'rejected', 'deleted', 'author gone', 'self', 'unmentioned', 'wrong identity', 'renamed', 'blocked', 'blocker']);

it('discards a generated answer if its parent changes while the model is running', function (string $change): void {
    $tom = User::factory()->create(['username' => 'tom', 'github_id' => CreateTomFriendship::GITHUB_ID]);
    $commit = Commit::factory()->create(['body' => '@tom hello', 'mentions' => ['tom' => $tom->id]]);
    Tom::fake(function () use ($commit, $tom, $change): string {
        match ($change) {
            'deleted' => $commit->delete(),
            'rejected' => $commit->update(['status' => CommitStatus::Rejected]),
            'blocked' => $commit->user->blocking()->attach($tom),
        };

        return 'A reply that must not be posted.';
    });

    resolve(CreateTomReply::class)->handle($commit->id);

    expect($commit->replies()->count())->toBe(0);
    Tom::assertPrompted('@tom hello');
})->with(['deleted', 'rejected', 'blocked']);

it('retries provider failures without retaining their content in the exception', function (): void {
    $tom = User::factory()->create(['username' => 'tom', 'github_id' => CreateTomFriendship::GITHUB_ID]);
    $commit = Commit::factory()->create(['body' => '@tom hello', 'mentions' => ['tom' => $tom->id]]);
    Tom::fake(function (): never {
        throw new RuntimeException('sensitive provider response');
    });

    $job = new ReplyAsTom($commit->id);
    expect(fn () => $job->handle(resolve(CreateTomReply::class)))
        ->toThrow(RuntimeException::class, 'Tom reply failed for commit '.$commit->id.'.');
    expect($commit->replies()->count())->toBe(0);

    Tom::fake(['Back online. :-D']);
    $job->handle(resolve(CreateTomReply::class));
    expect($commit->replies()->sole()->body)->toBe('Back online. :-D');
});
