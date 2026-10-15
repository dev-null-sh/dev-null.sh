<?php

declare(strict_types=1);

use App\Services\EmojiShortcodes;

it('replaces every shortcode with its emoji', function (): void {
    $emoji = resolve(EmojiShortcodes::class);

    expect($emoji->replace('shipped it :heart :like :dislike :laugh'))
        ->toBe('shipped it ❤️ 👍 👎 😂');
});

it('leaves a shortcode inside a longer word untouched', function (): void {
    $emoji = resolve(EmojiShortcodes::class);

    expect($emoji->replace(':heartbeat and :likewise stay put'))
        ->toBe(':heartbeat and :likewise stay put');
});

it('replaces shortcodes next to punctuation and the end of the text', function (): void {
    $emoji = resolve(EmojiShortcodes::class);

    expect($emoji->replace('one: :heart, two: :like!'))
        ->toBe('one: ❤️, two: 👍!');
});

it('does not modify text without shortcodes', function (): void {
    $emoji = resolve(EmojiShortcodes::class);

    expect($emoji->replace('plain text with a url https://example.com'))
        ->toBe('plain text with a url https://example.com');
});
