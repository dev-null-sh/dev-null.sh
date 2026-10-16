<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUserActivityGraph;
use App\Http\Requests\ShowUserActivityGraphRequest;
use App\Models\User;
use Illuminate\Http\Response;

final readonly class UserActivityGraphController
{
    public function show(ShowUserActivityGraphRequest $request, User $user, CreateUserActivityGraph $action): Response
    {
        return response($action->handle($user, $request->theme()), headers: [
            'Content-Type' => 'image/svg+xml',
        ]);
    }
}
