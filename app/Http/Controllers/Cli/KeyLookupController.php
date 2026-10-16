<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cli;

use App\Dto\SshPublicKey;
use App\Http\Requests\CreateKeyLookupRequest;
use Illuminate\Http\Response;

final readonly class KeyLookupController
{
    /**
     * Answers with the key that belongs to a devnull user, so the script only asks the SSH agent (or a hardware key) to sign with that one.
     */
    public function store(CreateKeyLookupRequest $request): Response
    {
        $publicKey = $request->publicKey();
        assert($publicKey instanceof SshPublicKey);

        return response($publicKey->toOpenSsh()."\n")->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
