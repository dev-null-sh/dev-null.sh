<?php

declare(strict_types=1);

use App\Services\CodeSpans;
use Tempest\Highlight\Highlighter;

it('highlights each kind of token', function (string $code, string $html): void {
    expect(resolve(Highlighter::class)->parse($code, 'sema'))->toBe($html);
})->with([
    'comment' => ['; budgets', '<span class="hl-comment">; budgets</span>'],
    'shebang' => ["#!/usr/bin/env sema\n1", "<span class=\"hl-comment\">#!/usr/bin/env sema</span>\n<span class=\"hl-number\">1</span>"],
    'string' => ['"Oslo"', '<span class="hl-value">&quot;Oslo&quot;</span>'],
    'regex literal' => ['#"^[a-z]{5}$"', '<span class="hl-value">#&quot;^[a-z]{5}$&quot;</span>'],
    'f-string' => ['f"hi ${name}"', '<span class="hl-value">f&quot;hi ${name}&quot;</span>'],
    'f-string holding strings and maps' => [
        'f"${(greet {:name "World"})}!"',
        '<span class="hl-value">f&quot;${(greet {:name &quot;World&quot;})}!&quot;</span>',
    ],
    'character' => ['#\space', '<span class="hl-value">#\space</span>'],
    'quote character' => ['#\"', '<span class="hl-value">#\&quot;</span>'],
    'special form' => ['(lambda (x) x)', '(<span class="hl-keyword">lambda</span> (x) x)'],
    'namespaced special form' => ['(llm/with-budget b)', '(<span class="hl-keyword">llm/with-budget</span> b)'],
    'threading macro' => ['(->> xs)', '(<span class="hl-keyword">-&gt;&gt;</span> xs)'],
    'defined name' => ['(defagent guide)', '(<span class="hl-keyword">defagent</span> <span class="hl-type">guide</span>)'],
    'defined function' => ['(define (greet name))', '(<span class="hl-keyword">define</span> (<span class="hl-type">greet</span> name))'],
    'namespaced defined function' => ['(define (gen/int lo))', '(<span class="hl-keyword">define</span> (<span class="hl-type">gen/int</span> lo))'],
    'redefined form' => ['(defmacro unless)', '(<span class="hl-keyword">defmacro</span> <span class="hl-type">unless</span>)'],
    'keyword' => ['{:max-turns 3}', '{<span class="hl-property">:max-turns</span> <span class="hl-number">3</span>}'],
    'keywords back to back' => [':a:b', '<span class="hl-property">:a</span><span class="hl-property">:b</span>'],
    'literals' => ['[#t #f nil]', '[<span class="hl-literal">#t</span> <span class="hl-literal">#f</span> <span class="hl-literal">nil</span>]'],
    'numbers' => ['[-1.5e3 1/2 #xFF]', '[<span class="hl-number">-1.5e3</span> <span class="hl-number">1/2</span> <span class="hl-number">#xFF</span>]'],
    'complex and prefixed numbers' => [
        '[3+4i +i #e1.5 #x-ff #e#xFF +inf]',
        '[<span class="hl-number">3+4i</span> <span class="hl-number">+i</span> <span class="hl-number">#e1.5</span> <span class="hl-number">#x-ff</span> <span class="hl-number">#e#xFF</span> <span class="hl-number">+inf</span>]',
    ],
    'namespaced call' => ['(llm/complete p)', '(<span class="hl-variable">llm/complete</span> p)'],
    'lambda arguments' => ['#(* % %2 %&)', '#(* <span class="hl-variable">%</span> <span class="hl-variable">%2</span> <span class="hl-variable">%&amp;</span>)'],
    'unicode symbols' => ['(:λ m)', '(<span class="hl-property">:λ</span> m)'],
]);

it('only matches whole atoms', function (): void {
    expect(resolve(Highlighter::class)->parse('(if-let x diff nil? 1st)', 'sema'))
        ->toBe('(<span class="hl-keyword">if-let</span> x diff nil? 1st)');
});

it('leaves quoted symbols as data', function (): void {
    expect(resolve(Highlighter::class)->parse("(list 'if 'async)", 'sema'))->toBe("(list 'if 'async)");
});

it('leaves characters the reader rejects alone', function (): void {
    expect(resolve(Highlighter::class)->parse('#\alarm', 'sema'))->toBe('#\alarm');
});

it('keeps strings, comments and characters apart', function (string $code, string $html): void {
    expect(resolve(Highlighter::class)->parse($code, 'sema'))->toBe($html);
})->with([
    'a semicolon in a string' => [
        '(display "a;b") ; done',
        '(display <span class="hl-value">&quot;a;b&quot;</span>) <span class="hl-comment">; done</span>',
    ],
    'a quote in a comment' => [
        "; say \"hi\n(f \"x\")",
        "<span class=\"hl-comment\">; say &quot;hi</span>\n(f <span class=\"hl-value\">&quot;x&quot;</span>)",
    ],
    'a quote character' => [
        '(= c #\") (f "x")',
        '(= c <span class="hl-value">#\&quot;</span>) (f <span class="hl-value">&quot;x&quot;</span>)',
    ],
    'a string across lines' => [
        "(f \"one;\ntwo\")\n; done",
        "(f <span class=\"hl-value\">&quot;one;\ntwo&quot;</span>)\n<span class=\"hl-comment\">; done</span>",
    ],
]);

it('reads maps as code rather than highlighter markup', function (): void {
    expect(resolve(Highlighter::class)->parse('{+ 1 :a 2 :}', 'sema'))
        ->toBe('{+ <span class="hl-number">1</span> <span class="hl-property">:a</span> <span class="hl-number">2</span> :}');
});

it('highlights sema code blocks in commits', function (): void {
    expect(resolve(CodeSpans::class)->highlight("```sema\n(defagent guide {:max-turns 3})\n```"))->toBe([
        [
            'type' => 'block',
            'language' => 'sema',
            'html' => '(<span class="hl-keyword">defagent</span> <span class="hl-type">guide</span> {<span class="hl-property">:max-turns</span> <span class="hl-number">3</span>})',
        ],
    ]);
});
