<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateExport;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

final readonly class ExportController
{
    /**
     * The same file as Download JSON in the web's settings, sharing its export limit.
     */
    public function show(#[CurrentUser] User $user, CreateExport $action): StreamedJsonResponse
    {
        return response()->streamJson($action->handle($user), headers: [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="devnull-export-'.now()->format('Y-m-d-His').'.json"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
