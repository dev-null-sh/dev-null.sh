<?php

declare(strict_types=1);

use App\Services\LinkPreviews\LimitedResponseBody;

it('retains short responses without changing their bytes', function (): void {
    $body = new LimitedResponseBody(32);
    $header = "GIF89a\xc2\x80\x01\x00\x80\x00\x00";

    expect($body->write($header))->toBe(13)
        ->and((string) $body)->toBe($header);
});

it('returns a short write so the transport aborts an oversized response', function (): void {
    $body = new LimitedResponseBody(32);

    expect($body->write('GIF89a'.str_repeat('x', 1_048_576)))->toBe(32)
        ->and($body->getSize())->toBe(32)
        ->and((string) $body)->toBe('GIF89a'.str_repeat('x', 26));
});

it('enforces the same cap across multiple writes', function (): void {
    $body = new LimitedResponseBody(32);

    expect($body->write(str_repeat('a', 20)))->toBe(20)
        ->and($body->write(str_repeat('b', 20)))->toBe(12)
        ->and($body->write('c'))->toBe(0)
        ->and($body->getSize())->toBe(32)
        ->and((string) $body)->toBe(str_repeat('a', 20).str_repeat('b', 12));
});

it('does not let a seek move writes past the byte cap', function (): void {
    $body = new LimitedResponseBody(32);
    $body->seek(64);

    expect($body->write('overflow'))->toBe(0)
        ->and($body->getSize())->toBe(0);
});
