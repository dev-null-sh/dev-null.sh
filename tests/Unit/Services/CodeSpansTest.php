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

it('highlights ruby code blocks and keeps the language label', function (string $language): void {
    $segments = resolve(CodeSpans::class)->highlight("```{$language}\nclass Greeter\n  def greet(name)\n    puts \"Hello\"\n  end\nend\n```");

    expect($segments[0]['language'])->toBe($language)
        ->and($segments[0]['html'])
        ->toContain('<span class="hl-keyword">class</span>')
        ->toContain('<span class="hl-type">Greeter</span>')
        ->toContain('<span class="hl-keyword">def</span>')
        ->toContain('<span class="hl-property">greet</span>')
        ->toContain('<span class="hl-value">&quot;Hello&quot;</span>')
        ->toContain('<span class="hl-keyword">end</span>');
})->with(['ruby', 'rb', 'Ruby', 'RUBY']);

it('highlights ruby values, variables, symbols and comments', function (): void {
    $code = <<<'RUBY'
        @name = 'Ruby'
        @@count = 42
        $debug = true
        options = { enabled: false, name: :ruby, value: nil }
        # class Greeter "hello"
        RUBY;

    $html = resolve(CodeSpans::class)->highlight("```ruby\n{$code}\n```")[0]['html'];

    expect($html)
        ->toContain('<span class="hl-variable">@name</span>')
        ->toContain('<span class="hl-variable">@@count</span>')
        ->toContain('<span class="hl-variable">$debug</span>')
        ->toContain('<span class="hl-value">\'Ruby\'</span>')
        ->toContain('<span class="hl-number">42</span>')
        ->toContain('<span class="hl-literal">true</span>')
        ->toContain('<span class="hl-literal">false</span>')
        ->toContain('<span class="hl-literal">nil</span>')
        ->toContain('<span class="hl-value">:ruby</span>')
        ->toContain('<span class="hl-comment"># class Greeter &quot;hello&quot;</span>');
});

it('escapes html in ruby strings and comments without highlighting their contents', function (): void {
    $code = <<<'RUBY'
        puts "<script>class Greeter # end</script>"
        # <img src=x onerror=alert(1)>
        RUBY;

    $html = resolve(CodeSpans::class)->highlight("```ruby\n{$code}\n```")[0]['html'];

    expect($html)
        ->toContain('<span class="hl-value">&quot;&lt;script&gt;class Greeter # end&lt;/script&gt;&quot;</span>')
        ->toContain('<span class="hl-comment"># &lt;img src=x onerror=alert(1)&gt;</span>')
        ->not->toContain('<script>', '<img', 'hl-keyword', 'hl-type');
});

it('keeps escaped quotes and comment markers inside ruby strings', function (): void {
    $code = <<<'RUBY'
        "say \"hello\" # class"; 'it\'s Ruby'
        RUBY;

    $html = resolve(CodeSpans::class)->highlight("```ruby\n{$code}\n```")[0]['html'];

    expect($html)
        ->toContain('<span class="hl-value">&quot;say \\&quot;hello\\&quot; # class&quot;</span>')
        ->toContain('<span class="hl-value">\'it\\\'s Ruby\'</span>')
        ->not->toContain('hl-comment', 'hl-keyword', 'hl-type');
});

it('highlights ruby block comments without highlighting their contents', function (): void {
    $code = "=begin\nclass Greeter\n=end\nclass Hello\nend";
    $html = resolve(CodeSpans::class)->highlight("```ruby\n{$code}\n```")[0]['html'];

    expect($html)
        ->toContain("<span class=\"hl-comment\">=begin\nclass Greeter\n=end</span>")
        ->toContain('<span class="hl-type">Hello</span>')
        ->not->toContain('<span class="hl-type">Greeter</span>');
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
