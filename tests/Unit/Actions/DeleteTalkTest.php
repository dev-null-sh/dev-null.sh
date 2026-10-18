<?php

declare(strict_types=1);

use App\Actions\CreateTalk;
use App\Actions\DeleteTalk;
use App\Enums\TalkVisibility;
use App\Jobs\EmbedCommit;
use App\Jobs\ModerateCommit;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\Talk;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake([UnfurlLink::class, ModerateCommit::class, EmbedCommit::class]);
});

it('deletes a talk and soft deletes its anchor commit', function (): void {
    $user = User::factory()->create();
    $talk = resolve(CreateTalk::class)->handle(
        $user,
        'Delete me',
        'This talk should not stick around.',
        now()->subDay()->toImmutable(),
        slidesUrl: 'https://example.com/talk.pdf',
    );
    $commitId = $talk->commit_id;
    assert(is_string($commitId));

    resolve(DeleteTalk::class)->handle($talk);

    expect(Talk::query()->whereKey($talk->id)->exists())->toBeFalse()
        ->and(Commit::withTrashed()->findOrFail($commitId)->trashed())->toBeTrue();
});

it('deletes a draft without a commit', function (): void {
    $user = User::factory()->create();
    $talk = resolve(CreateTalk::class)->handle(
        $user,
        'Draft delete',
        'No commit yet.',
        now()->addDay()->toImmutable(),
        slidesUrl: 'https://example.com/draft',
        visibility: TalkVisibility::Private,
    );

    resolve(DeleteTalk::class)->handle($talk);

    expect(Talk::query()->whereKey($talk->id)->exists())->toBeFalse()
        ->and(Commit::withTrashed()->count())->toBe(0);
});
