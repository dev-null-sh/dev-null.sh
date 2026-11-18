<?php

declare(strict_types=1);

use App\Enums\ApiDocsSection;
use App\Models\User;

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
        ->get(route('api-docs.show', ApiDocsSection::Introduction))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('api-docs/show'));
});

it('lists the guides in reading order', function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::Introduction))
        ->assertInertia(fn ($page) => $page
            ->has('sections', 1)
            ->where('sections.0', [
                'slug' => 'introduction',
                'title' => 'Introduction',
                'description' => 'What the API is for, and where it lives',
            ]));
});

it('gives a guide its code blocks', function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::Introduction))
        ->assertInertia(fn ($page) => $page
            ->has('snippets', 1)
            ->has('snippets.firstRequest', fn ($snippet) => $snippet->where('language', 'bash')->has('html')));
});

it('answers a section the docs do not have with the not found page', function (): void {
    $this->get('/docs/api/webhooks')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
});

it('describes each section to search engines and link previews', function (): void {
    $this->get(route('api-docs.show', ApiDocsSection::Introduction))
        ->assertOk()
        ->assertSee('<title>Introduction - API docs - /dev/null</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('api-docs.show', ApiDocsSection::Introduction).'">', false);
});
