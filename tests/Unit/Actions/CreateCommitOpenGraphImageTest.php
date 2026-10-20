<?php

declare(strict_types=1);

use App\Actions\CreateCommitOpenGraphImage;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Services\OpenGraphCanvas;
use Illuminate\Support\Facades\Cache;

it('fits commits that would overflow the card', function (string $body): void {
    $commit = Commit::factory()->for(User::factory()->withoutProfile())->create(['body' => $body]);

    $image = resolve(CreateCommitOpenGraphImage::class)->handle($commit->user, $commit);

    expect(array_slice((array) getimagesizefromstring($image), 0, 2))->toBe([1200, 630]);
})->with([
    'a one-liner' => ['Hello, world.'],
    'a long body with a url' => [str_repeat('Writing PHP on purpose and shipping it before lunch. ', 3).'https://github.com/nunomaduro/dev-null.sh/pull/57/files#diff-0123456789abcdef'],
    'an unbroken word wider than the card' => [str_repeat('supercalifragilistic', 14)],
    'paragraphs' => ["First line.\n\nSecond line.\nThird line.\n\n\nFourth line.\nFifth line.\nSixth line.\nSeventh line."],
    'nothing Geist can draw' => ['🚀🔥✨'],
    'a code block' => ["```php\necho 'Hello, world.';\n```"],
    'prose before a code block' => [str_repeat('Writing PHP on purpose and shipping it before lunch. ', 3)."\n```php\necho 1;\n```\nAfter."],
    'inline code before a code block' => ["Run `composer test` first:\n```\ncomposer test\n```"],
    'blank lines around the code' => ["```js\n\n\nconst a = 1;\n\n\n```"],
    'a code line wider than the card' => ["```\n".str_repeat('x', 500)."\n```"],
    'a code line wider than the card at every size but the smallest' => ["```\n".str_repeat('x', 70)."\n```"],
    'more code than fits' => ["```php\n".str_repeat("echo 1;\n", 200).'```'],
    'code with crlf' => ["```php\r\necho 1;\r\n```"],
    'an empty code block' => ["Nothing here:\n```\n```"],
    'two code blocks' => ["```php\necho 1;\n```\nand\n```js\nconsole.log(1);\n```"],
]);

it('draws the first code block in a window', function (string $body, bool $isDrawn): void {
    $commit = Commit::factory()->for(User::factory()->withoutProfile())->create(['body' => $body]);

    $image = imagecreatefromstring(resolve(CreateCommitOpenGraphImage::class)->handle($commit->user, $commit));

    assert($image instanceof GdImage);

    $colors = [];

    for ($y = 0; $y < OpenGraphCanvas::FOOTER_RULE_Y; $y++) {
        $colors[] = imagecolorat($image, OpenGraphCanvas::CONTENT_X + 8, $y);
    }

    expect(in_array(0x18181A, $colors, true))->toBe($isDrawn);
})->with([
    'a code block' => ["```php\necho 1;\n```", true],
    'prose before a code block' => ["Look:\n```php\necho 1;\n```", true],
    'inline code only' => ['Run `composer test` first.', false],
    'an empty code block' => ["```\n\n```", false],
    'no code' => ['Hello, world.', false],
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
