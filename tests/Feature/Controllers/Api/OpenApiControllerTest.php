<?php

declare(strict_types=1);

it('serves the committed spec to anyone, unsigned', function (): void {
    $response = $this->get(route('api-openapi.show'))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/json')
        ->and($response->getContent())->toBe(file_get_contents(resource_path('api/openapi.json')));
});

it('lets clients and caches keep the spec for an hour', function (): void {
    $response = $this->get(route('api-openapi.show'))->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('public', 'max-age=3600')
        ->and($response->headers->get('ETag'))->not->toBeNull();
});

it('lives beside the API it describes', function (): void {
    expect(route('api-openapi.show', absolute: false))->toBe('/api/v1/openapi.json');
});
