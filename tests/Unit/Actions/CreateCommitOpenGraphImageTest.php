<?php

declare(strict_types=1);

use App\Actions\CreateCommitOpenGraphImage;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('fits commits that would overflow the card', function (string $body): void {
    $commit = Commit::factory()->for(User::factory()->withoutProfile())->create(['body' => $body]);

    $image = resolve(CreateCommitOpenGraphImage::class)->handle($commit);

    expect(array_slice((array) getimagesizefromstring($image), 0, 2))->toBe([1200, 630]);
})->with([
    'a one-liner' => ['Hello, world.'],
    'a long body with a url' => [str_repeat('Writing PHP on purpose and shipping it before lunch. ', 4).'https://github.com/nunomaduro/devnull.com/pull/57/files#diff-0123456789abcdef'],
    'an unbroken word wider than the card' => [str_repeat('supercalifragilistic', 14)],
    'paragraphs' => ["First line.\n\nSecond line.\nThird line.\n\n\nFourth line.\nFifth line.\nSixth line.\nSeventh line."],
    'nothing Geist can draw' => ['🚀🔥✨'],
]);

it('draws the parent of a reply only when it is approved', function (CommitStatus $status, bool $isDrawn): void {
    $parent = Commit::factory()->create(['status' => $status]);
    $reply = Commit::factory()->for($parent, 'parent')->for(User::factory()->withoutProfile())->create(['body' => 'Agreed.']);
    $action = resolve(CreateCommitOpenGraphImage::class);

    $asReply = $action->handle($reply);

    Cache::flush();
    $reply->update(['parent_id' => null]);

    expect($action->handle($reply->fresh()) !== $asReply)->toBe($isDrawn);
})->with([
    'approved' => [CommitStatus::Approved, true],
    'pending' => [CommitStatus::Pending, false],
    'rejected' => [CommitStatus::Rejected, false],
]);

it('draws the number of approved replies', function (): void {
    $commit = Commit::factory()->for(User::factory()->withoutProfile())->create(['body' => 'Hello, world.']);
    $action = resolve(CreateCommitOpenGraphImage::class);

    $withoutReplies = $action->handle($commit);

    Commit::factory()->for($commit, 'parent')->pending()->create();
    Cache::flush();

    expect($action->handle($commit->fresh()))->toBe($withoutReplies);

    Commit::factory()->for($commit, 'parent')->create();
    Cache::flush();

    expect($action->handle($commit->fresh()))->not->toBe($withoutReplies);
});

it('draws the content warning instead of the body', function (): void {
    $commit = Commit::factory()->for(User::factory()->withoutProfile())->create([
        'body' => 'Mark is still on the testing floor.',
        'content_warning' => 'spoilers: severance s3',
    ]);
    $action = resolve(CreateCommitOpenGraphImage::class);

    $behindWarning = $action->handle($commit);

    Cache::flush();
    $commit->update(['body' => 'Something else entirely.']);

    expect($action->handle($commit->fresh()))->toBe($behindWarning);
});

it('draws spoilers hidden', function (string $body): void {
    $commit = Commit::factory()->for(User::factory()->withoutProfile())->create(['body' => $body]);
    $action = resolve(CreateCommitOpenGraphImage::class);

    $hidden = $action->handle($commit);

    Cache::flush();
    $commit->update(['body' => 'The ending: ||it was a dream all along||']);

    expect($action->handle($commit->fresh()))->toBe($hidden);
})->with([
    'text' => 'The ending: ||the butler did it||',
    'url' => 'The ending: ||https://example.com/the-secret-ending||',
]);

it('shortens visible urls in the image', function (): void {
    $commit = Commit::factory()->for(User::factory()->withoutProfile())->create([
        'body' => 'Read https://github.com/nunomaduro/dev-null.sh/pulls',
    ]);
    $action = resolve(CreateCommitOpenGraphImage::class);
    $image = $action->handle($commit);

    Cache::flush();
    $commit->update(['body' => 'Read github.com/nunomaduro/dev-nul…']);

    expect($action->handle($commit->fresh()))->toBe($image);
});
