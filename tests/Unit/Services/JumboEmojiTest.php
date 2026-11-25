<?php

declare(strict_types=1);

use App\Services\JumboEmoji;

/**
 * The same cases tests/Unit/JumboEmoji.test.tsx runs on the web's count, so API clients and the web agree.
 *
 * @return list<array{string, int}>
 */
function sharedJumboEmojiCases(): array
{
    // Datasets load before the application boots, so the fixture is read by path.
    /** @var list<array{string, int}> $cases */
    $cases = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/jumbo-emoji.json'), true, flags: JSON_THROW_ON_ERROR);

    return $cases;
}

it('counts a body of only emoji as the web does', function (string $body, int $count): void {
    $emoji = resolve(JumboEmoji::class);

    expect($emoji->emojiOnlyCount($body))->toBe($count)
        ->and($emoji->count($body))->toBe($count <= JumboEmoji::MAX ? $count : 0);
})->with(sharedJumboEmojiCases(...));

it('counts unicode whitespace between emoji as space', function (): void {
    expect(resolve(JumboEmoji::class)->count("🔥\u{00A0}\u{2003}🚀"))->toBe(2);
});
