<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ExportPersonalData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

final readonly class PersonalDataExportController
{
    public function show(#[CurrentUser] User $user, ExportPersonalData $action): StreamedJsonResponse
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
