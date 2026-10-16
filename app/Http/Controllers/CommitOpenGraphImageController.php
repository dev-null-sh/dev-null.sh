<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitOpenGraphImage;
use App\Http\Requests\ShowCommitOpenGraphImageRequest;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Http\Response;

final readonly class CommitOpenGraphImageController
{
    public function show(ShowCommitOpenGraphImageRequest $request, User $user, Commit $commit, CreateCommitOpenGraphImage $action): Response
    {
        return response($action->handle($user, $commit), headers: [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
