<?php

declare(strict_types=1);

use App\Dto\AuthorizationCode;

it('keys a code by its hash, never by the code itself', function (): void {
    expect(AuthorizationCode::keyFor('the-code'))->toBe('authorization-code:'.hash('sha256', 'the-code'))
        ->not->toContain('the-code');
});

it('reads back the code it stored', function (): void {
    $code = new AuthorizationCode('authorization-code:key', 'user-id', 'SHA256:key', 'devnull on iPhone', 'challenge');

    expect(AuthorizationCode::fromArray($code->key, $code->toArray()))->toEqual($code);
});

it('makes the pkce s256 challenge of a verifier', function (): void {
    // The example in RFC 7636, appendix B...
    expect(AuthorizationCode::challengeFor('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'))
        ->toBe('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
});
