<?php

declare(strict_types=1);

use App\Ai\Tools\SearchCommits;
use App\Contracts\Services\EmbeddingProvider;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Tools\Request as ToolRequest;

it('tells Tom the search is down instead of failing his reply', function (): void {
    Exceptions::fake();
    $this->mock(EmbeddingProvider::class)->shouldReceive('embed')->andThrow(new RuntimeException('OpenAI is down'));

    expect(new SearchCommits()->handle(new ToolRequest(['query' => 'deploys'])))
        ->toBe('Search is unavailable right now. Answer without it.');

    Exceptions::assertReported(RuntimeException::class);
});
