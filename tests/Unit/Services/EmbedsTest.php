<?php

declare(strict_types=1);

use App\Services\Embeds;

it('finds no embed in a body that does not end with the command', function (string $body): void {
    $embeds = resolve(Embeds::class);

    expect($embeds->extract($body))->toBeNull();
})->with([
    'plain text' => 'Shipped the new log today',
    'command before more text' => "/embed https://example.com\nand then some",
    'command without a url' => 'Try this /embed',
    'command glued to a word' => 'See example.com/embed https://example.com',
    'other command' => '/embedded https://example.com',
    'url on the next line' => "/embed\nhttps://example.com",
    'more than a ratio after the url' => '/embed https://example.com 16:9 please',
]);

it('splits the embed off the end of the body', function (string $body, string $remaining): void {
    $embeds = resolve(Embeds::class);

    expect($embeds->extract($body))->toBe([
        'body' => $remaining,
        'url' => 'https://example.com/game',
        'ratio' => '1:1',
    ]);
})->with([
    'own line' => ["Here's my cool new game\n\n/embed https://example.com/game", "Here's my cool new game"],
    'same line' => ['Play it /embed https://example.com/game', 'Play it'],
    'trailing whitespace' => ["Play it\n/embed\thttps://example.com/game  \n\n", 'Play it'],
    'only the last command' => ["/embed https://first.example.com\n/embed https://example.com/game", '/embed https://first.example.com'],
    'command only' => ['/embed https://example.com/game', ''],
]);

it('takes the ratio after the url', function (string $ratio): void {
    $embeds = resolve(Embeds::class);

    expect($embeds->extract("Play it\n/embed https://example.com/game {$ratio}  "))->toBe([
        'body' => 'Play it',
        'url' => 'https://example.com/game',
        'ratio' => $ratio,
    ]);
})->with(['16:9', '9:16', '4:3', '21:9', '1:4', '4:1', '100:100']);

it('only allows ratios from 1:4 to 4:1', function (string $ratio): void {
    $embeds = resolve(Embeds::class);

    expect($embeds->extract("Play it /embed https://example.com/game {$ratio}"))->toBe([
        'body' => 'Play it',
        'url' => 'https://example.com/game',
        'ratio' => null,
    ]);
})->with(['16x9', 'wide', '16:', ':9', '0:1', '1:0', '09:16', '1.5:1', '5:1', '1:5', '1000:1000']);

it('normalizes the embed url', function (): void {
    $embeds = resolve(Embeds::class);

    expect($embeds->extract('Play /embed HTTPS://Bücher.Example/Game')['url'])
        ->toBe('https://xn--bcher-kva.example/Game');
});

it('only embeds https urls on other sites', function (string $url): void {
    config(['app.url' => 'https://devnull.example']);

    $embeds = resolve(Embeds::class);

    expect($embeds->extract("Play it\n/embed {$url}"))->toBe([
        'body' => 'Play it',
        'url' => null,
        'ratio' => '1:1',
    ]);
})->with([
    'http' => 'http://example.com/game',
    'javascript' => 'javascript:alert(1)',
    'data' => 'data:text/html,<script>alert(1)</script>',
    'no scheme' => 'example.com/game',
    'username' => 'https://user@example.com/game',
    'password' => 'https://:secret@example.com/game',
    'this site' => 'https://devnull.example/@nunomaduro',
]);
