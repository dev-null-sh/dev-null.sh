<?php

declare(strict_types=1);

use App\Actions\VerifySignedCommit;

it('serves the script', function (): void {
    $response = $this->get(route('cli-script.show'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    expect($response->getContent())
        ->toStartWith("#!/usr/bin/env bash\n")
        ->toContain('curl -fsSL '.url('/').'/commit | bash')
        ->toContain('DEVNULL_URL="${DEVNULL_URL:-'.url('/').'}"')
        ->not->toContain('__DEVNULL_URL__');
});

it('signs with the namespace the server verifies', function (): void {
    expect($this->get(route('cli-script.show'))->getContent())
        ->toContain('NAMESPACE="'.VerifySignedCommit::Namespace.'"');
});
