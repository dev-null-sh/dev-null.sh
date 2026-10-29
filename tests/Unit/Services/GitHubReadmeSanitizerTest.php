<?php

declare(strict_types=1);

use App\Services\GitHubReadmeSanitizer;

it('strips the constructs that have broken sanitizers before', function (string $payload): void {
    $clean = (new GitHubReadmeSanitizer)->sanitize($payload);

    expect($clean)->not->toMatch('/<script|<iframe|<object|<embed|<svg|<math|<form|<style|<template|srcdoc|javascript:|\son[a-z]+\s*=|data:/i');
})->with([
    'a script tag' => '<script>alert(1)</script>',
    'an image that throws' => '<img src=x onerror=alert(1)>',
    'a javascript link' => '<a href="javascript:alert(1)">click</a>',
    'an svg holding a style' => '<svg><style><img src=x onerror=alert(1)></style></svg>',
    'the math mutation' => '<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>',
    'an iframe with its own document' => '<iframe srcdoc="<script>alert(1)</script>"></iframe>',
    'a toggle handler' => '<details open ontoggle=alert(1)>x</details>',
    'a form with a button' => '<form action="https://evil.example"><button>go</button></form>',
    'a base tag' => '<base href="https://evil.example/">',
    'a data uri image' => '<img src="data:image/svg+xml,<svg onload=alert(1)>">',
    'a template' => '<template><img src=x onerror=alert(1)></template>',
    'an object' => '<object data="x"></object>',
    'an inline style' => '<div style="position:fixed;inset:0">overlay</div>',
    'an attribute that only looks harmless' => '<p title="x" onclick="alert(1)">text</p>',
]);

it('keeps the formatting a README is written with', function (): void {
    $clean = (new GitHubReadmeSanitizer)->sanitize('<h1 align="center">Hi</h1><p>Text <strong>bold</strong> and <code>x</code></p><ul><li>one</li></ul>');

    expect($clean)
        ->toContain('<h1 align="center">Hi</h1>')
        ->toContain('<strong>bold</strong>')
        ->toContain('<code>x</code>')
        ->toContain('<li>one</li>');
});

it('forces a link to open safely, whatever it asked for', function (): void {
    $clean = (new GitHubReadmeSanitizer)->sanitize('<a href="https://ok.example" target="_self" rel="opener">ok</a>');

    expect($clean)
        ->toContain('rel="noopener noreferrer nofollow ugc"')
        ->toContain('target="_blank"')
        ->not->toContain('rel="opener"')
        ->not->toContain('_self');
});

it('drops a destination that is not an absolute http, https or mailto link', function (string $href): void {
    expect((new GitHubReadmeSanitizer)->sanitize("<a href=\"{$href}\">x</a>"))->not->toContain('href');
})->with([
    'a protocol relative link' => '//evil.example/x',
    'a javascript link' => 'javascript:alert(1)',
    'a relative path' => '/relative/path',
    'another scheme' => 'ftp://evil.example/x',
]);

it('keeps a mailto link, which a README may legitimately use', function (): void {
    expect((new GitHubReadmeSanitizer)->sanitize('<a href="mailto:someone@example.com">mail</a>'))
        ->toContain('href="mailto:');
});

it('serves an image only from a host GitHub proxies or serves itself', function (): void {
    $sanitizer = new GitHubReadmeSanitizer;

    expect($sanitizer->sanitize('<img src="https://evil.example/x.png" alt="x">'))->not->toContain('evil.example')
        ->and($sanitizer->sanitize('<img src="https://camo.githubusercontent.com/abc" alt="badge">'))->toContain('camo.githubusercontent.com')
        ->and($sanitizer->sanitize('<img src="https://raw.githubusercontent.com/a/b/main/c.png">'))->toContain('raw.githubusercontent.com');
});

it('keeps the words of a wrapper it does not know', function (): void {
    expect((new GitHubReadmeSanitizer)->sanitize('<g-emoji>hi</g-emoji>'))->toBe('hi');
});

it('cuts a document longer than it is willing to parse', function (): void {
    $clean = (new GitHubReadmeSanitizer)->sanitize('<p>'.str_repeat('a', 400_000).'</p>');

    expect(mb_strlen($clean))->toBeLessThan(210_000);
});
