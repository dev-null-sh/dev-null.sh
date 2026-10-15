<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RenderCommitOpenGraphImage;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Http\Response;

final readonly class CommitOpenGraphImageController
{
    public function show(User $user, Commit $commit, RenderCommitOpenGraphImage $action): Response
    {
        abort_unless($commit->status === CommitStatus::Approved, 404);

        return response($action->handle($commit), headers: [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
