<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\LinkPreviewAddress;
use App\Dto\LinkPreviewMetadata;
use App\Models\LinkPreview;
use App\Services\LinkPreviewParser;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Uri\WhatWg\Url;

final readonly class UpdateLinkPreview
{
    private const int MAX_REDIRECTS = 3;

    private const int MAX_BODY_BYTES = 1_048_576;

    public function __construct(private LinkPreviewAddress $addresses, private LinkPreviewParser $parser)
    {
        //
    }

    /**
     * Fetches the page behind the link and stores its Open Graph metadata. A page that can't or mustn't be fetched is
     * marked as fetched without metadata, so it is not tried again. Failures that may pass, such as connection errors,
     * timeouts, rate limits and server errors, are thrown instead, so the caller can try again later.
     */
    public function handle(LinkPreview $linkPreview): void
    {
        $metadata = $this->fetchMetadata($linkPreview->url);

        $linkPreview->update([...$metadata->toArray(), 'fetched_at' => now()]);
    }

    /**
     * Follows up to three redirects by hand, so that every hop goes through the same checks as the original URL.
     */
    private function fetchMetadata(string $url): LinkPreviewMetadata
    {
        $page = Url::parse($url);

        for ($hop = 0; $hop <= self::MAX_REDIRECTS && $page instanceof Url; $hop++) {
            $address = $this->addresses->get($page);

            if ($address === null) {
                return new LinkPreviewMetadata;
            }

            $response = $this->get($page, $address);

            if ($response->serverError() || $response->tooManyRequests()) {
                $response->throw();
            }

            if (! $response->redirect()) {
                return $response->successful() && $this->isHtml($response)
                    ? $this->parser->parse($this->body($response), $page)
                    : new LinkPreviewMetadata;
            }

            $page = Url::parse($response->header('Location'), $page);
        }

        return new LinkPreviewMetadata;
    }

    /**
     * Connects to the address that was checked rather than resolving the host again, so DNS rebinding can't swap in a
     * private address. The empty host and port in CURLOPT_CONNECT_TO match every request, so the pin holds even if
     * cURL were to read the URL differently than we do. Bodies are cut off after 1 MB, and a body that was cut off
     * (by that limit or by the timeout) is still used, as metadata lives at the top of the page. English is asked for
     * explicitly, as sites such as YouTube otherwise pick the language from the server's location.
     */
    private function get(Url $url, string $address): Response
    {
        $port = $url->getPort() ?? ($url->getScheme() === 'https' ? 443 : 80);

        $request = Http::withUserAgent(sprintf('Mozilla/5.0 (compatible; devnull-linkpreview/1.0; +%s)', config()->string('app.url')))
            ->accept('text/html')
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->connectTimeout(3)
            ->timeout(5)
            ->withoutRedirecting()
            ->withOptions([
                'decode_content' => false,
                'progress' => fn (int $expected, int $downloaded): bool => $downloaded > self::MAX_BODY_BYTES,
                'curl' => [
                    CURLOPT_CONNECT_TO => [sprintf('::%s:%d', str_contains($address, ':') ? "[{$address}]" : $address, $port)],
                ],
            ]);

        try {
            return $request->get($url->toAsciiString());
        } catch (ConnectionException $connectionException) {
            $cutOff = $connectionException->getPrevious();

            if ($cutOff instanceof ResponseException) {
                return new Response($cutOff->getResponse());
            }

            throw $connectionException;
        }
    }

    private function isHtml(Response $response): bool
    {
        $mediaType = mb_strtolower(mb_trim(Str::before($response->header('Content-Type'), ';')));

        return in_array($mediaType, ['text/html', 'application/xhtml+xml'], true);
    }

    private function body(Response $response): string
    {
        $body = $response->toPsrResponse()->getBody();
        $body->rewind();

        return Utils::copyToString($body, self::MAX_BODY_BYTES);
    }

}
