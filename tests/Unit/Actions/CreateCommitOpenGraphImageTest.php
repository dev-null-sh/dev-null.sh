<?php

declare(strict_types=1);

use App\Actions\CreateCommitOpenGraphImage;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('fits commits that would overflow the card', function (string $body): void {
    $commit = Commit::factory()->for(User::factory()->withoutProfile())->create(['body' => $body]);

    $image = resolve(CreateCommitOpenGraphImage::class)->handle($commit->user, $commit);

    expect(array_slice((array) getimagesizefromstring($image), 0, 2))->toBe([1200, 630]);
})->with([
    'a one-liner' => ['Hello, world.'],
    'a long body with a url' => [str_repeat('Writing PHP on purpose and shipping it before lunch. ', 3).'https://github.com/dev-null-sh/dev-null.sh/pull/57/files#diff-0123456789abcdef'],
    'an unbroken word wider than the card' => [str_repeat('supercalifragilistic', 14)],
    'paragraphs' => ["First line.\n\nSecond line.\nThird line.\n\n\nFourth line.\nFifth line.\nSixth line.\nSeventh line."],
    'nothing Geist can draw' => ['🚀🔥✨'],
]);

it('draws the parent of a reply only when it is approved', function (CommitStatus $status, bool $isDrawn): void {
    $parent = Commit::factory()->create(['status' => $status]);
    $reply = Commit::factory()->for($parent, 'parent')->for(User::factory()->withoutProfile())->create(['body' => 'Agreed.']);
    $action = resolve(CreateCommitOpenGraphImage::class);

    $asReply = $action->handle($reply->user, $reply);

    Cache::flush();
    $reply->update(['parent_id' => null]);

    expect($action->handle($reply->user, $reply->fresh()) !== $asReply)->toBe($isDrawn);
})->with([
    'approved' => [CommitStatus::Approved, true],
    'pending' => [CommitStatus::Pending, false],
    'rejected' => [CommitStatus::Rejected, false],
]);

it('draws the number of approved replies', function (): void {
    $commit = Commit::factory()->for(User::factory()->withoutProfile())->create(['body' => 'Hello, world.']);
    $action = resolve(CreateCommitOpenGraphImage::class);

    $withoutReplies = $action->handle($commit->user, $commit);

    Commit::factory()->for($commit, 'parent')->pending()->create();
    Cache::flush();

    expect($action->handle($commit->user, $commit->fresh()))->toBe($withoutReplies);

    Commit::factory()->for($commit, 'parent')->create();
    Cache::flush();

    expect($action->handle($commit->user, $commit->fresh()))->not->toBe($withoutReplies);
});
