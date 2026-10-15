<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RenderOpenGraphImage;
use App\Models\User;
use Illuminate\Http\Response;

final readonly class UserOpenGraphImageController
{
    public function show(User $user, RenderOpenGraphImage $action): Response
    {
        return response($action->handle($user), headers: [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
