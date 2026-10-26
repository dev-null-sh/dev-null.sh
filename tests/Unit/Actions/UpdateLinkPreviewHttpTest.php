<?php

declare(strict_types=1);

use App\Actions\UpdateLinkPreview;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Uri\WhatWg\Url;

it('recovers a bounded GIF response when a server ignores Range', function (): void {
    $requestLog = tempnam(sys_get_temp_dir(), 'gif-preview-http-');
    throw_if($requestLog === false, RuntimeException::class, 'Unable to create GIF request log.');

    $server = new Process([PHP_BINARY, base_path('tests/Fixtures/link-preview-http.php'), $requestLog]);
    $server->setTimeout(5)->start();

    try {
        $server->waitUntil(
            static fn (string $type, string $output): bool => $type === Process::OUT && mb_trim($output) !== '',
        );
        $port = (int) mb_trim($server->getOutput());
        throw_if($port === 0, RuntimeException::class, 'GIF fixture did not report a port.');
        Http::allowStrayRequests(["http://example.test:{$port}/*"]);

        $response = new ReflectionMethod(UpdateLinkPreview::class, 'get')->invoke(
            resolve(UpdateLinkPreview::class),
            Url::parse("http://example.test:{$port}/cat.gif"),
            '127.0.0.1',
            true,
        );
        assert($response instanceof Response);

        expect($response->status())->toBe(200)
            ->and($response->header('Content-Type'))->toBe('image/gif')
            ->and($response->body())->toHaveLength(32)
            ->and($response->body())->toBe("GIF89a\x01\x00\x01\x00\x80\x00\x00".str_repeat('x', 19))
            ->and(file_get_contents($requestLog))->toContain("Range: bytes=0-31\r\n");
    } finally {
        $server->stop();
        File::delete($requestLog);
    }
});
