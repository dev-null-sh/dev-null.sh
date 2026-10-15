<?php

declare(strict_types=1);

use App\Actions\ExtractCommitTopics;
use App\Ai\Agents\TopicExtractor;

it('extracts the topics of a commit with Claude', function (): void {
    TopicExtractor::fake([['topics' => ['laravel', 'rust']]]);

    $topics = resolve(ExtractCommitTopics::class)->handle('Just shipped a Laravel queue driver written in Rust');

    expect($topics)->toBe(['laravel', 'rust']);

    TopicExtractor::assertPrompted('Just shipped a Laravel queue driver written in Rust');
});

it('normalizes the topics', function (): void {
    TopicExtractor::fake([['topics' => [' Laravel ', 'laravel', '', 42, str_repeat('a', 60), 'php', 'go']]]);

    $topics = resolve(ExtractCommitTopics::class)->handle('Hello');

    expect($topics)->toBe(['laravel', str_repeat('a', 50), 'php']);
});

it('fails when Claude returns malformed topics', function (): void {
    TopicExtractor::fake([['tags' => ['laravel']]]);

    resolve(ExtractCommitTopics::class)->handle('Hello');
})->throws(RuntimeException::class, 'Claude returned malformed topics.');

it('fails when Claude returns no structured output', function (): void {
    TopicExtractor::fake(['laravel, rust']);

    resolve(ExtractCommitTopics::class)->handle('Hello');
})->throws(RuntimeException::class, 'Claude returned no structured output.');
