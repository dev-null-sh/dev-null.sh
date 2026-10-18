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
            'html' => '<span class="hl-variable">$name</span> = <span class="hl-value">\'devnull\'</span>;',
        ],
    ]);
});

it('drops the line breaks around a code block', function (string $lineBreak): void {
    $codeSpans = resolve(CodeSpans::class);

    $segments = $codeSpans->highlight(implode($lineBreak, ['before', '```', 'x', 'y', '```', 'after', '', 'end']));

    expect($segments)->toBe([
        ['type' => 'text', 'text' => 'before'],
        ['type' => 'block', 'language' => null, 'html' => "x\ny"],
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
        ['type' => 'block', 'language' => $language, 'html' => '&lt;script&gt;alert(1)&lt;/script&gt;'],
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
        ['type' => 'block', 'language' => 'PHP', 'html' => '<span class="hl-variable">$name</span>;'],
    ]);
});

it('takes the language from the first word of the fence', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("```php title=\"app.php\"\n\$name;\n```"))->toBe([
        ['type' => 'block', 'language' => 'php', 'html' => '<span class="hl-variable">$name</span>;'],
    ]);
});

it('keeps an empty code block', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("```\n```"))->toBe([
        ['type' => 'block', 'language' => null, 'html' => ''],
    ]);
});

it('accepts fences of more than three backticks', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("feature:\n````php\n\$login;\n````"))->toBe([
        ['type' => 'text', 'text' => 'feature:'],
        ['type' => 'block', 'language' => 'php', 'html' => '<span class="hl-variable">$login</span>;'],
    ])->and($codeSpans->highlight("```\n// test\n````"))->toBe([
        ['type' => 'block', 'language' => null, 'html' => '// test'],
    ]);
});

it('takes a details block as a part of its own', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->split("look:\n<details>\n<summary>Why</summary>\n\nbody\n</details>\nnice"))->toBe([
        "look:\n",
        "<details>\n<summary>Why</summary>\n\nbody\n</details>",
        "\nnice",
    ]);
});

it('leaves something that only looks like a details block as prose', function (string $text): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->split($text))->toBe([$text]);
})->with([
    'never closed' => "<details>\n<summary>Why</summary>\n\nbody",
    'no summary' => "<details>\n\nbody\n</details>",
    'summary on the line the block opens with' => "<details><summary>Why</summary>\n\nbody\n</details>",
    'attributes' => "<details open>\n<summary>Why</summary>\n\nbody\n</details>",
    'indented' => " <details>\n<summary>Why</summary>\n\nbody\n</details>",
    'closed on the summary line' => "<details>\n<summary>Why</summary></details>",
]);

it('reads a details block as a segment holding its summary and its own segments', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    $segments = $codeSpans->highlight("<details>\n<summary>Why it took all day</summary>\n\nIt was DNS `dig`.\n\n```php\necho 1;\n```\n</details>");

    expect($segments)->toBe([
        [
            'type' => 'details',
            'summary' => 'Why it took all day',
            'segments' => [
                ['type' => 'text', 'text' => 'It was DNS '],
                ['type' => 'code', 'code' => 'dig'],
                ['type' => 'text', 'text' => ".\n"],
                ['type' => 'block', 'language' => 'php', 'html' => '<span class="hl-keyword">echo</span> 1;'],
            ],
        ],
    ]);
});

it('drops the line breaks around a details block', function (string $lineBreak): void {
    $codeSpans = resolve(CodeSpans::class);

    $segments = $codeSpans->highlight(implode($lineBreak, ['before', '<details>', '<summary>Why</summary>', '', 'body', '</details>', 'after']));

    expect($segments)->toBe([
        ['type' => 'text', 'text' => 'before'],
        ['type' => 'details', 'summary' => 'Why', 'segments' => [['type' => 'text', 'text' => 'body']]],
        ['type' => 'text', 'text' => 'after'],
    ]);
})->with([
    'lf' => "\n",
    'crlf' => "\r\n",
]);

it('keeps a body of two details blocks apart', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->highlight("<details>\n<summary>a</summary>\n\nb\n</details>\n\n<details>\n<summary>c</summary>\n\nd\n</details>"))->toBe([
        ['type' => 'details', 'summary' => 'a', 'segments' => [['type' => 'text', 'text' => 'b']]],
        ['type' => 'details', 'summary' => 'c', 'segments' => [['type' => 'text', 'text' => 'd']]],
    ]);
});

it('maps the prose in a details block, leaving its tags and its code as written', function (string $lineBreak): void {
    $codeSpans = resolve(CodeSpans::class);

    $block = implode($lineBreak, ['<details>', '<summary>hi</summary>', '', 'hi `hi`', '</details>']);

    expect($codeSpans->mapProse($block, mb_strtoupper(...)))
        ->toBe(implode($lineBreak, ['<details>', '<summary>HI</summary>', '', 'HI `hi`', '</details>']));
})->with([
    'lf' => "\n",
    'crlf' => "\r\n",
]);

it('keeps the summary and the prose of a details block when the code comes off', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->withoutCode("<details>\n<summary>hi</summary>\n\nsee `a.com` and b.com\n</details>"))
        ->toBe("\nhi\n\nsee \n and b.com\n\n");
});

it('takes the summary of a details block, and nothing from anything else', function (): void {
    $codeSpans = resolve(CodeSpans::class);

    expect($codeSpans->summary("<details>\n<summary>Why</summary>\n\nbody\n</details>"))->toBe('Why')
        ->and($codeSpans->summary('`code`'))->toBeNull()
        ->and($codeSpans->summary("```php\necho 1;\n```"))->toBeNull();
});
