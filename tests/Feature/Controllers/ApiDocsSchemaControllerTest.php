<?php

declare(strict_types=1);

use App\Enums\ApiDocsSection;

it('gives an endpoint the JSON schemas of its body and of its detailed answers', function (): void {
    $response = $this->getJson(route('api-docs-schema.show', [ApiDocsSection::Commits, 'api-commit.store']))
        ->assertOk()
        ->assertJsonStructure(['body' => ['code', 'html'], 'responses' => ['201' => ['code', 'html']]]);

    expect(json_decode($response->json('body.code'), true)['properties'])->toHaveKey('body');
});

it('answers an endpoint the group does not have with not found', function (): void {
    $this->getJson(route('api-docs-schema.show', [ApiDocsSection::Users, 'api-commit.store']))->assertNotFound();
    $this->getJson(route('api-docs-schema.show', [ApiDocsSection::Users, 'nothing']))->assertNotFound();
});

it('answers an endpoint no group could have with not found', function (): void {
    $this->get('/docs/api/users/schemas/'.str_repeat('a', 300))->assertNotFound();
    $this->get('/docs/api/users/schemas/api user')->assertNotFound();
});

it("lets browsers and caches keep an endpoint's schemas for an hour, the same for everyone", function (): void {
    $response = $this->get(route('api-docs-schema.show', [ApiDocsSection::Users, 'api-user.show']))->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('public', 'max-age=3600')
        ->and($response->headers->get('ETag'))->not->toBeNull()
        ->and($response->headers->getCookies())->toBe([]);
});
