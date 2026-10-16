<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ssh;

use App\Http\Requests\ShowKeyOwnerRequest;
use App\Queries\SshKeyOwnerQuery;
use Illuminate\Http\JsonResponse;

final readonly class KeyOwnerController
{
    /**
     * Answers `ssh:serve` with the username that owns an SSH key, so it never has to query the database itself.
     */
    public function show(ShowKeyOwnerRequest $request, SshKeyOwnerQuery $query): JsonResponse
    {
        return response()->json(['username' => $query->find($request->fingerprint())]);
    }
}
