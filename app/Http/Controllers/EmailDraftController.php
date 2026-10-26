<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateEmailDraftRequest;
use App\Services\EmailDrafts;
use Illuminate\Http\JsonResponse;

final readonly class EmailDraftController
{
    public function store(CreateEmailDraftRequest $request, EmailDrafts $drafts): JsonResponse
    {
        return response()->json($drafts->generate($request->draft()));
    }
}
