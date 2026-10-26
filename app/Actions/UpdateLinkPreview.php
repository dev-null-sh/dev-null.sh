<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\LinkPreviewMetadata;
use App\Dto\LinkPreviewRequest;
use App\Models\LinkPreview;
use App\Services\LinkPreviews\LinkPreviewAddress;
use App\Services\LinkPreviews\LinkPreviewBudget;
use App\Services\LinkPreviews\LinkPreviewHttpClient;
use App\Services\LinkPreviews\LinkPreviewParser;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Uri\WhatWg\Url;

final readonly class UpdateLinkPreview
{
    private const int MAX_REDIRECTS = 3;

    private const int MAX_BODY_BYTES = 1_048_576;

    private const string HEADER_ACCEPT = 'Accept';

    private const string HTML_CONTENT_TYPE = 'text/html';

    public function __construct(
        private LinkPreviewAddress $addresses,
        private LinkPreviewBudget $budget,
        private LinkPreviewHttpClient $http,
        private LinkPreviewParser $parser,
    ) {
        //
    }

    public function handle(LinkPreview $linkPreview): void
    {
        $metadata = $this->fetchMetadata($linkPreview->url);

        $linkPreview->update([...$metadata->toArray(), 'fetched_at' => now()]);
    }

    private function fetchMetadata(string $url): LinkPreviewMetadata
    {
        $page = Url::parse($url);
        $deadline = $this->budget->start();
        $visited = [];

        for ($hop = 0; $hop <= self::MAX_REDIRECTS && $page instanceof Url; $hop++) {
            $source = $page->withFragment(null)->toAsciiString();

            if (isset($visited[$source])) {
                return new LinkPreviewMetadata;
            }

            $visited[$source] = true;
            $request = $this->prepareRequest($page, $deadline);

            if (! $request instanceof LinkPreviewRequest) {
                return new LinkPreviewMetadata;
            }

            $response = $this->http->get($request);
            $response->throwIf($response->serverError() || $response->tooManyRequests());

            if (! $response->redirect()) {
                return $this->responseMetadata($response, $page);
            }

            $page = $this->redirectPage($response, $page);
        }

        return new LinkPreviewMetadata;
    }

    private function prepareRequest(Url $page, float $deadline): ?LinkPreviewRequest
    {
        $address = $this->addresses->get($page);
        $remaining = $this->budget->remaining($deadline);

        if ($address === null || $remaining <= 0) {
            return null;
        }

        return new LinkPreviewRequest(
            url: $page,
            address: $address,
            headers: [self::HEADER_ACCEPT => self::HTML_CONTENT_TYPE],
            maxBodyBytes: self::MAX_BODY_BYTES,
            timeoutSeconds: $remaining,
        );
    }

    private function redirectPage(Response $response, Url $page): ?Url
    {
        $location = mb_trim($response->header('Location'));

        if ($location === '') {
            return null;
        }

        return Url::parse($location, $page);
    }

    private function responseMetadata(Response $response, Url $page): LinkPreviewMetadata
    {
        if (! $response->successful()) {
            return new LinkPreviewMetadata;
        }

        if (! $this->isHtml($response)) {
            return new LinkPreviewMetadata;
        }

        return $this->parser->parse($this->body($response), $page);
    }

    private function isHtml(Response $response): bool
    {
        $mediaType = mb_strtolower(mb_trim(Str::before($response->header('Content-Type'), ';')));

        return in_array($mediaType, [self::HTML_CONTENT_TYPE, 'application/xhtml+xml'], true);
    }

    private function body(Response $response): string
    {
        $body = $response->toPsrResponse()->getBody();
        $body->rewind();

        return Utils::copyToString($body, self::MAX_BODY_BYTES);
    }
}
