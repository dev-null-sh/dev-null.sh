<?php

declare(strict_types=1);

use App\Enums\ApiDocsSection;
use App\Services\ApiGuideSnippets;

it('gives each guide its code, highlighted as every code block of the docs is', function (ApiDocsSection $section, array $snippets): void {
    $given = resolve(ApiGuideSnippets::class)->for($section);

    expect(array_keys($given))->toBe(array_keys($snippets))
        ->and(array_map(fn (array $snippet): string => $snippet['language'], $given))->toBe($snippets);

    foreach ($given as $snippet) {
        expect($snippet['html'])->not->toBeEmpty();
    }
})->with([
    'introduction' => [ApiDocsSection::Introduction, ['firstRequest' => 'bash']],
    'authentication' => [ApiDocsSection::Authentication, ['keygen' => 'bash', 'message' => 'text']],
    'pagination' => [ApiDocsSection::Pagination, ['page' => 'json']],
    'errors' => [ApiDocsSection::Errors, ['error' => 'json', 'validation' => 'json']],
    'a group of the reference' => [ApiDocsSection::Users, []],
]);

it('shows the first request against the production API', function (): void {
    expect(resolve(ApiGuideSnippets::class)->for(ApiDocsSection::Introduction)['firstRequest']['html'])
        ->toContain('curl')
        ->toContain('https://dev-null.sh/api/v1/users/nunomaduro')
        ->toContain('Accept: application/json');
});

it('escapes what the message to sign holds', function (): void {
    expect(resolve(ApiGuideSnippets::class)->for(ApiDocsSection::Authentication)['message']['html'])
        ->toBe("&lt;unix timestamp&gt;\n&lt;nonce&gt;\n&lt;METHOD&gt;\n&lt;path and query&gt;\n&lt;body&gt;");
});
