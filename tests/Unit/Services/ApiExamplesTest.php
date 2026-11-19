<?php

declare(strict_types=1);

use App\Services\ApiExamples;

it('shows once how each client signs a request', function (): void {
    $signing = resolve(ApiExamples::class)->signing();

    expect(array_column($signing, 'label'))->toBe(['curl', 'Laravel', 'fetch'])
        ->and(array_column($signing, 'language'))->toBe(['bash', 'php', 'javascript'])
        ->and($signing[0]['code'])->toStartWith("# devnull_sign METHOD PATH [BODY] sets \$ts, \$nonce and \$signature\ndevnull_sign() {")
        ->and($signing[1]['code'])->toContain('function devnullHeaders(string $method, string $path, string $body = \'\'): array')
        ->and($signing[2]['code'])->toContain("export function devnullHeaders(method, path, body = '') {")
        ->and($signing[1]['html'])->toContain('<span class="hl-keyword">function</span>');
});
