<?php

declare(strict_types=1);

use App\Services\CommitLinks;

it('uses the commit route parameters for a link on this site', function (string $suffix): void {
    config(['app.url' => 'https://dev-null.sh']);
    $id = '123e4567-e89b-12d3-a456-426614174000';

    expect(resolve(CommitLinks::class)->previewUrl("https://dev-null.sh/@author/commits/{$id}{$suffix}"))
        ->toBe(route('commit-preview.show', ['user' => 'author', 'commit' => $id], absolute: false));
})->with(['', '?from=log#reply', '/']);

it('does not resolve other sites or routes as commit previews', function (string $url): void {
    config(['app.url' => 'https://dev-null.sh']);

    expect(resolve(CommitLinks::class)->previewUrl($url))->toBeNull();
})->with([
    'invalid url' => 'not a url',
    'other scheme' => 'ftp://dev-null.sh/@author/commits/123e4567-e89b-12d3-a456-426614174000',
    'other host' => 'https://example.com/@author/commits/123e4567-e89b-12d3-a456-426614174000',
    'lookalike host' => 'https://dev-null.sh.example.com/@author/commits/123e4567-e89b-12d3-a456-426614174000',
    'other port' => 'https://dev-null.sh:8443/@author/commits/123e4567-e89b-12d3-a456-426614174000',
    'profile' => 'https://dev-null.sh/@author',
    'invalid id' => 'https://dev-null.sh/@author/commits/not-a-uuid',
    'photo' => 'https://dev-null.sh/@author/commits/123e4567-e89b-12d3-a456-426614174000/photos/1',
]);
