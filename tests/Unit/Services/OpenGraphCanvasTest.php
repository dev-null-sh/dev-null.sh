<?php

declare(strict_types=1);

use App\Services\CodeSpans;
use App\Services\HighlightedCode;
use App\Services\OpenGraphCanvas;

/**
 * Draws the code block in a window from (184, 60) to (1104, 360), with its code from (208, 108) and 872 wide.
 */
function drawCodeWindow(string $body): GdImage
{
    [$block] = resolve(CodeSpans::class)->highlight($body);

    assert($block['type'] === 'block');

    $canvas = new OpenGraphCanvas;
    $canvas->window(184, 60, 920, 300, $block['language']);
    $canvas->code(resolve(HighlightedCode::class)->lines($block['html']), 22, 32, 208, 108, 872);

    $image = imagecreatefromstring($canvas->png());

    assert($image instanceof GdImage);

    return $image;
}

/**
 * Every color in the rectangle, once each.
 *
 * @return list<int>
 */
function colorsIn(GdImage $image, int $x1, int $y1, int $x2, int $y2): array
{
    $colors = [];

    for ($y = $y1; $y < $y2; $y++) {
        for ($x = $x1; $x < $x2; $x++) {
            $colors[(int) imagecolorat($image, $x, $y)] = true;
        }
    }

    return array_keys($colors);
}

it('draws a dark window with three dots in the lanes', function (): void {
    $image = drawCodeWindow("```php\necho 1;\n```");

    expect(imagecolorat($image, 644, 352))->toBe(0x18181A)
        ->and(imagecolorat($image, 214, 84))->toBe(0xD9480F)
        ->and(imagecolorat($image, 238, 84))->toBe(0xB7791F)
        ->and(imagecolorat($image, 262, 84))->toBe(0x2F9E44)
        ->and(imagecolorat($image, 185, 61))->toBe(0xF6F7F8);
});

it('names the language in the title bar only when the block has one', function (string $body, bool $isNamed): void {
    $image = drawCodeWindow($body);

    expect(colorsIn($image, 984, 68, 1096, 100) !== [0x18181A])->toBe($isNamed);
})->with([
    'a language' => ["```php\necho 1;\n```", true],
    'no language' => ["```\necho 1;\n```", false],
    'a language GeistMono has no glyphs for' => ["```🚀\necho 1;\n```", false],
]);

it('draws each token in its color', function (): void {
    $image = drawCodeWindow("```php\nreturn \$user;\n```");

    expect(colorsIn($image, 208, 108, 288, 140))->toContain(0x7AA2F7)
        ->and(colorsIn($image, 298, 108, 388, 140))->toContain(0xF06595);
});

it('cuts a line wider than the window instead of drawing past it', function (): void {
    $image = drawCodeWindow("```\n".str_repeat('x', 500)."\n```");

    expect(colorsIn($image, 1040, 108, 1080, 140))->not->toBe([0x18181A])
        ->and(colorsIn($image, 1082, 108, 1102, 140))->toBe([0x18181A]);
});

it('keeps the indentation of each line', function (): void {
    $image = drawCodeWindow("```\nx\n\tx\n```");

    expect(colorsIn($image, 208, 140, 248, 172))->toBe([0x18181A])
        ->and(colorsIn($image, 248, 140, 288, 172))->not->toBe([0x18181A]);
});

it('draws nothing for emoji, which GeistMono has no glyphs for', function (): void {
    $image = drawCodeWindow("```\n🚀🔥\n```");

    expect(colorsIn($image, 208, 108, 1080, 140))->toBe([0x18181A]);
});
