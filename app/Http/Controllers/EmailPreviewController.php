<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateEmailPreviewRequest;
use App\Mail\AdminEmail;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class EmailPreviewController
{
    /**
     * The email exactly as it arrives, rendered by the same template the mail is sent with.
     */
    public function store(CreateEmailPreviewRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json([
            'html' => new AdminEmail($user, $request->string('subject')->value(), $request->string('body')->value())->render(),
        ]);
    }
}
