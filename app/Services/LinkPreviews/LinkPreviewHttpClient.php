<?php

declare(strict_types=1);

namespace App\Services\LinkPreviews;

use App\Dto\LinkPreviewRequest;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final readonly class LinkPreviewHttpClient
{
    public const int HTTP_PORT = 80;

    public const int HTTPS_PORT = 443;

    private const int CONNECT_TIMEOUT_SECONDS = 3;

    private const string USER_AGENT_FORMAT = 'Mozilla/5.0 (compatible; devnull-linkpreview/1.0; +%s)';

    private const array DEFAULT_HEADERS = ['Accept-Language' => 'en-US,en;q=0.9'];

    public function get(LinkPreviewRequest $request): Response
    {
        $port = $request->url->getPort() ?? ($request->url->getScheme() === 'https' ? self::HTTPS_PORT : self::HTTP_PORT);
        $connection = sprintf('::%s:%d', str_contains($request->address, ':') ? "[{$request->address}]" : $request->address, $port);

        try {
            return Http::withUserAgent(sprintf(self::USER_AGENT_FORMAT, config()->string('app.url')))
                ->withHeaders([...self::DEFAULT_HEADERS, ...$request->headers])
                ->connectTimeout(min(self::CONNECT_TIMEOUT_SECONDS, $request->timeoutSeconds))
                ->timeout($request->timeoutSeconds)
                ->withoutRedirecting()
                ->withOptions([
                    RequestOptions::DECODE_CONTENT => false,
                    RequestOptions::SINK => new LimitedResponseBody($request->maxBodyBytes),
                    RequestOptions::CURL => [CURLOPT_CONNECT_TO => [$connection]],
                ])
                ->get($request->url->toAsciiString());
        } catch (ConnectionException $connectionException) {
            $previous = $connectionException->getPrevious();

            throw_unless($previous instanceof ResponseException, $connectionException);

            return new Response($previous->getResponse());
        }
    }
}
