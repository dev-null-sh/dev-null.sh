<?php

declare(strict_types=1);

use App\Actions\ExtractCommitTopics;

it('extracts the topics of a commit with Claude', function (): void {
    $requests = fakeClaude(['topics' => ['laravel', 'rust']]);

    $topics = resolve(ExtractCommitTopics::class)->handle('Just shipped a Laravel queue driver written in Rust');

    $body = json_decode((string) $requests[0]->getBody(), true);

    expect($topics)->toBe(['laravel', 'rust'])
        ->and($body['model'])->toBe('claude-opus-5-5')
        ->and($body['messages'][0]['content'])->toBe('Just shipped a Laravel queue driver written in Rust')
        ->and($body['output_config']['format']['type'])->toBe('json_schema')
        ->and($requests[0]->getHeaderLine('anthropic-beta'))->toContain('server-side-fallback-2026-07-01');
});

it('normalizes the topics', function (): void {
    fakeClaude(['topics' => [' Laravel ', 'laravel', '', 42, str_repeat('a', 60), 'php', 'go']]);

    $topics = resolve(ExtractCommitTopics::class)->handle('Hello');

    expect($topics)->toBe(['laravel', str_repeat('a', 50), 'php']);
});

it('fails when Claude declines', function (): void {
    fakeClaude(['topics' => []], 'refusal');

    resolve(ExtractCommitTopics::class)->handle('Hello');
})->throws(RuntimeException::class, 'Claude declined to extract topics.');

it('fails when Claude returns no text', function (): void {
    fakeClaude(null);

    resolve(ExtractCommitTopics::class)->handle('Hello');
})->throws(RuntimeException::class, 'Claude returned no topics.');

it('fails when Claude returns malformed topics', function (): void {
    fakeClaude(['tags' => ['laravel']]);

    resolve(ExtractCommitTopics::class)->handle('Hello');
})->throws(RuntimeException::class, 'Claude returned malformed topics.');
