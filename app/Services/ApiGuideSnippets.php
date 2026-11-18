<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApiDocsSection;
use Tempest\Highlight\Highlighter;

/**
 * The code the API docs' guides show, highlighted on the server, so a guide shows it in the same code block as
 * everywhere else on the site.
 */
final readonly class ApiGuideSnippets
{
    public const string BaseUrl = 'https://dev-null.sh/api/v1';

    public function __construct(private Highlighter $highlighter)
    {
        //
    }

    /**
     * @return array<string, array{language: string, html: string}>
     */
    public function for(ApiDocsSection $section): array
    {
        $snippets = match ($section) {
            ApiDocsSection::Introduction => [
                'firstRequest' => ['bash', 'curl '.self::BaseUrl."/users/nunomaduro \\\n  -H 'Accept: application/json'"],
            ],
        };

        return array_map(fn (array $snippet): array => [
            'language' => $snippet[0],
            'html' => $this->highlighter->parse($snippet[1], $snippet[0]),
        ], $snippets);
    }
}
