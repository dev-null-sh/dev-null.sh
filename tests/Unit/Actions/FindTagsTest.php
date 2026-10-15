<?php

declare(strict_types=1);

use App\Actions\FindTags;

it('finds no tags in plain text', function (string $text): void {
    $action = resolve(FindTags::class);

    expect($action->handle($text))->toBe([]);
})->with([
    'empty' => '',
    'words' => 'Shipped the new log today',
    'hash alone' => 'Press # to continue',
    'issue reference' => 'Fixed in #123',
    'starts with a digit' => 'Ranked #1password and #2fa',
    'mid-word' => 'Writing C# and a#b today',
    'url fragment' => 'Read https://laravel.com/docs#queues and https://x.com/#foo',
    'bare domain fragment' => 'See laravel.com/#queues',
    'html entity' => 'Escaped &#x27;quotes&#x27;',
    'double hash' => 'Markdown ##heading',
    'heading' => '# Release notes',
    'too long' => '#'.str_repeat('a', 51),
]);

it('finds tags', function (string $text, array $tags): void {
    $action = resolve(FindTags::class);

    expect($action->handle($text))->toBe($tags);
})->with([
    'single' => ['Shipping #laravel today', ['laravel']],
    'at the start' => ['#til queues can be batched', ['til']],
    'several' => ['#php and #rust in one day', ['php', 'rust']],
    'lowercased' => ['Loving #Laravel and #PHP', ['laravel', 'php']],
    'deduplicated' => ['#php #PHP #Php', ['php']],
    'digits, dashes and underscores' => ['#php85 #laravel-13 #dev_null', ['php85', 'laravel-13', 'dev_null']],
    'trailing punctuation' => ['Done with #php. Next: #rust! (#go), "#zig", #ruby?', ['php', 'rust', 'go', 'zig', 'ruby']],
    'trailing dash or underscore' => ['#php- and #rust_', ['php', 'rust']],
    'stops at a dot' => ['Trying #node.js', ['node']],
    'after a newline' => ["Release notes\n#changelog", ['changelog']],
    'next to a url' => ['https://laravel.com #laravel', ['laravel']],
    'longest allowed' => ['#'.str_repeat('a', 50), [str_repeat('a', 50)]],
]);
