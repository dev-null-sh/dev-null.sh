<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApiDocsSection;
use Tempest\Highlight\Highlighter;

/**
 * The code the API docs' guides show, highlighted as the reference's code blocks are, so a guide shows it in the same
 * block as everywhere else in the docs.
 */
final readonly class ApiGuideSnippets
{
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
                'firstRequest' => ['bash', 'curl '.ApiExamples::BaseUrl."/users/nunomaduro \\\n  -H 'Accept: application/json'"],
            ],
            ApiDocsSection::Authentication => [
                'keygen' => ['bash', "ssh-keygen -t ed25519 -f ~/.ssh/devnull -N ''"],
                'message' => ['text', "<unix timestamp>\n<nonce>\n<METHOD>\n<path and query>\n<body>"],
            ],
            ApiDocsSection::Pagination => [
                'page' => ['json', $this->page()],
            ],
            ApiDocsSection::Errors => [
                'error' => ['json', <<<'JSON'
                    {
                      "message": "This commit isn't available.",
                      "code": "commit_unavailable"
                    }
                    JSON],
                'validation' => ['json', <<<'JSON'
                    {
                      "message": "The body field is required.",
                      "errors": {
                        "body": ["The body field is required."]
                      }
                    }
                    JSON],
            ],
            default => [],
        };

        return array_map(fn (array $snippet): array => [
            'language' => $snippet[0],
            'html' => $this->highlighter->parse($snippet[1], $snippet[0]),
        ], $snippets);
    }

    private function page(): string
    {
        $url = ApiExamples::BaseUrl;

        return <<<JSON
            {
              "data": [ … ],
              "links": {
                "first": null,
                "last": null,
                "prev": null,
                "next": "{$url}/users/nunomaduro/commits?cursor=eyJjcmVhdGVkX2F0Ijoi…"
              },
              "meta": {
                "path": "{$url}/users/nunomaduro/commits",
                "per_page": 20,
                "next_cursor": "eyJjcmVhdGVkX2F0Ijoi…",
                "prev_cursor": null
              }
            }
            JSON;
    }
}
