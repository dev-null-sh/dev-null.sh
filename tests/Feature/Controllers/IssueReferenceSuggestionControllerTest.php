<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('suggests issue and pull references for a number', function (): void {
    Http::fake([
        'api.github.com/repos/nunomaduro/dev-null.sh/issues/722' => Http::response([
            'number' => 722,
            'title' => 'Automatically link PRs and issues',
            'html_url' => 'https://github.com/nunomaduro/dev-null.sh/issues/722',
        ]),
        'api.github.com/search/issues*' => Http::response([
            'incomplete_results' => false,
            'items' => [],
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson('/search/issue-references?q=722')
        ->assertOk()
        ->assertJson([
            'references' => [
                [
                    'number' => 722,
                    'title' => 'Automatically link PRs and issues',
                    'url' => 'https://github.com/nunomaduro/dev-null.sh/issues/722',
                    'kind' => 'issue',
                ],
            ],
        ]);
});

it('requires digits', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/search/issue-references?q=abc')
        ->assertUnprocessable();
});

it('requires authentication', function (): void {
    $this->getJson('/search/issue-references?q=1')->assertUnauthorized();
});
