<?php

declare(strict_types=1);

use App\Services\CodeSpans;
use App\Services\HighlightedCode;

it('reads the runs of each line with the classes around them', function (): void {
    $lines = resolve(HighlightedCode::class)->lines('<span class="hl-keyword">return</span> <span class="hl-injection"><span class="hl-type">string</span> $name</span>;');

    expect($lines)->toBe([[
        ['text' => 'return', 'classes' => ['hl-keyword']],
        ['text' => ' ', 'classes' => []],
        ['text' => 'string', 'classes' => ['hl-injection', 'hl-type']],
        ['text' => ' $name', 'classes' => ['hl-injection']],
        ['text' => ';', 'classes' => []],
    ]]);
});

it('keeps the classes of a span that runs across lines', function (): void {
    $lines = resolve(HighlightedCode::class)->lines("a\n<span class=\"hl-comment\">/*\n * b\n */</span>\n\nc");

    expect($lines)->toBe([
        [['text' => 'a', 'classes' => []]],
        [['text' => '/*', 'classes' => ['hl-comment']]],
        [['text' => ' * b', 'classes' => ['hl-comment']]],
        [['text' => ' */', 'classes' => ['hl-comment']]],
        [],
        [['text' => 'c', 'classes' => []]],
    ]);
});

it('decodes the escaped text', function (): void {
    $lines = resolve(HighlightedCode::class)->lines('<span class="hl-keyword">&lt;?php</span> &quot;a&quot; &amp;&amp; &#039;b&#039;');

    expect(array_column($lines[0], 'text'))->toBe(['<?php', ' "a" && \'b\'']);
});

it('reads spans without a class, and with several', function (): void {
    $lines = resolve(HighlightedCode::class)->lines('<span>a</span><span class="hl-value hl-literal">b</span>');

    expect($lines)->toBe([[
        ['text' => 'a', 'classes' => []],
        ['text' => 'b', 'classes' => ['hl-value', 'hl-literal']],
    ]]);
});

it('expands tabs to the next tab stop', function (string $html, string $line): void {
    $lines = resolve(HighlightedCode::class)->lines($html);

    expect(implode('', array_column($lines[0], 'text')))->toBe($line);
})->with([
    'a leading tab' => ["\treturn;", '    return;'],
    'two leading tabs' => ["\t\treturn;", '        return;'],
    'a tab after text' => ["ab\tc", 'ab  c'],
    'a tab after a span' => ["<span class=\"hl-keyword\">abc</span>\td", 'abc d'],
    'no tabs' => ['a  b', 'a  b'],
]);

it('reads nothing from an empty block', function (): void {
    expect(resolve(HighlightedCode::class)->lines(''))->toBe([[]]);
});

it('reads back the code the highlighter was given', function (string $language, string $code): void {
    [$block] = resolve(CodeSpans::class)->highlight("```{$language}\n{$code}\n```");

    assert($block['type'] === 'block');

    $lines = resolve(HighlightedCode::class)->lines($block['html']);

    expect(implode("\n", array_map(fn (array $runs): string => implode('', array_column($runs, 'text')), $lines)))->toBe($code);
})->with([
    'php' => ['php', "<?php\n\n// greet\nfunction greet(string \$name): string\n{\n    return \"Hello, {\$name}!\";\n}"],
    'javascript' => ['js', 'const a = `b` + 1; // c'],
    'an unknown language' => ['brainfuck', '++[>+<-] <b>&amp;</b>'],
]);
