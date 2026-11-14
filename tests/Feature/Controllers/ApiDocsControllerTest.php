<?php

declare(strict_types=1);

use App\Enums\ApiDocsSection;
use App\Enums\ApiErrorCode;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Queries\ApiDocsQuery;
use App\Queries\ApiRateLimitsQuery;

it('opens the API docs on their introduction', function (): void {
    $this->get('/docs/api')->assertRedirect(route('api-docs.show', ApiDocsSection::Introduction));
});

it('renders every section of the API docs for guests', function (ApiDocsSection $section): void {
    $this->get(route('api-docs.show', $section))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('api-docs/show')
            ->where('current', $section->value));
})->with(ApiDocsSection::cases());

it('renders the API docs for signed in users', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('api-docs.show', ApiDocsSection::Users))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('api-docs/show'));
});

it('lists the guides in reading order, then the groups of the reference alphabetically', function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::Introduction))
        ->assertInertia(fn ($page) => $page
            ->has('sections', 15)
            ->where('sections.0', [
                'slug' => 'introduction',
                'title' => 'Introduction',
                'description' => 'What the API is for, and where it lives',
                'kind' => 'guide',
            ])
            ->where('sections.4.slug', 'errors')
            ->where('sections.5', [
                'slug' => 'commits',
                'title' => 'Commits',
                'description' => 'Reading, writing, starring and replying to commits',
                'kind' => 'reference',
            ])
            ->where('sections', fn ($sections): bool => collect($sections)->slice(5)->pluck('slug')->all() === [
                'commits', 'headlines', 'issues', 'me', 'notifications', 'organizations', 'remotes', 'scheduled-commits', 'search', 'users',
            ]));
});

it('lists the endpoints of a group of the reference', function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::Users))
        ->assertInertia(fn ($page) => $page
            ->where('endpoints.0.method', 'GET')
            ->where('endpoints.0.path', '/users/{handle}')
            ->where('endpoints.0.signature', 'optional'));
});

it('indexes every endpoint on every page, for searching them', function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::Introduction))
        ->assertInertia(fn ($page) => $page
            ->has('index', count(resolve(ApiDocsQuery::class)->index()))
            ->where('index.0.section', 'commits')
            ->where('index.0.path', '/commits/{commit}'));
});

it('sends the index once, leaving it out of the pages a visit goes on to', function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::Commits), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Except-Once-Props' => 'index',
    ])->assertOk()->assertJsonMissingPath('props.index');
});

it("gives a guide its code blocks, and the reference's groups none", function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::Errors))
        ->assertInertia(fn ($page) => $page
            ->has('snippets', 2)
            ->has('snippets.error', fn ($snippet) => $snippet->where('language', 'json')->has('html')));

    $this->get(route('api-docs.show', ApiDocsSection::Users))
        ->assertInertia(fn ($page) => $page->where('snippets', []));
});

it('lists the rate limits as the code sets them, in Rate limits only', function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::RateLimits))
        ->assertInertia(fn ($page) => $page
            ->has('rateLimits', count(resolve(ApiRateLimitsQuery::class)->get()))
            ->where('rateLimits.1', ['what' => 'Signed requests', 'limit' => 120, 'per' => 'minute, per person']));

    $this->get(route('api-docs.show', ApiDocsSection::Users))
        ->assertInertia(fn ($page) => $page->where('rateLimits', []));
});

it('shows how each client signs a request in Authentication only', function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::Authentication))
        ->assertInertia(fn ($page) => $page
            ->has('signing', 3)
            ->where('signing.1.label', 'Laravel'));

    $this->get(route('api-docs.show', ApiDocsSection::Users))
        ->assertInertia(fn ($page) => $page->where('signing', []));
});

it('lists no endpoints in a guide', function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::Authentication))
        ->assertInertia(fn ($page) => $page->where('endpoints', []));
});

it('lists every error code with the status it comes with', function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::Errors))
        ->assertInertia(fn ($page) => $page
            ->has('errorCodes', count(ApiErrorCode::cases()))
            ->where('errorCodes.0', ['code' => 'invalid_signature', 'status' => 401, 'meaning' => ApiErrorCode::InvalidSignature->meaning()])
            ->where('errorCodes.2.code', 'invalid_grant')
            ->where('errorCodes.2.status', 400));

    $this->get(route('api-docs.show', ApiDocsSection::Users))
        ->assertInertia(fn ($page) => $page->where('errorCodes', []));
});

it('says what every error code means, and where it can come from', function (ApiErrorCode $code): void {
    expect($code->meaning())->not->toBeEmpty();
})->with(ApiErrorCode::cases());

it('says which errors only come from one place', function (): void {
    expect(ApiErrorCode::OwnCommit->meaning())->toBe('You cannot report your own commit.')
        ->and(ApiErrorCode::CommitUnavailable->meaning())->toContain('report')
        ->and(ApiErrorCode::InvalidGrant->meaning())->toContain('another key');
});

it('answers a section the docs do not have with the not found page', function (): void {
    $this->get('/docs/api/webhooks')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
});

it('describes each section to search engines and link previews', function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::RateLimits))
        ->assertOk()
        ->assertSee('<title>Rate limits - API docs - /dev/null</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('api-docs.show', ApiDocsSection::RateLimits).'">', false);
});

it('names the reference after the groups of the OpenAPI spec', function (): void {
    $tags = array_column(json_decode((string) file_get_contents(resource_path('api/openapi.json')), true)['tags'], 'name');

    expect(array_map(fn (ApiDocsSection $section): string => $section->title(), ApiDocsSection::reference()))->toBe($tags);
});
