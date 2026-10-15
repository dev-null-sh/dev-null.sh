<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RenderUserOpenGraphImage;
use App\Models\User;
use Illuminate\Http\Response;

final readonly class UserOpenGraphImageController
{
    public function show(User $user, RenderUserOpenGraphImage $action): Response
    {
        return response($action->handle($user), headers: [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
