<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitVideo;
use App\Actions\DeleteCommitVideo;
use App\Http\Requests\CreateCommitVideoRequest;
use App\Http\Requests\DeleteCommitVideoRequest;
use App\Http\Requests\ShowCommitVideoRequest;
use App\Models\CommitVideo;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final readonly class CommitVideoController
{
    public function store(CreateCommitVideoRequest $request, #[CurrentUser] User $user, CreateCommitVideo $action): JsonResponse
    {
        return response()->json($action->handle($user, $request->integer('size')), 201);
    }

    public function show(ShowCommitVideoRequest $request, CommitVideo $commitVideo): JsonResponse
    {
        return response()->json(['video' => $commitVideo])->header('Cache-Control', 'private, no-store');
    }

    public function destroy(DeleteCommitVideoRequest $request, CommitVideo $commitVideo, DeleteCommitVideo $action): Response
    {
        $action->handle($commitVideo);

        return response()->noContent();
    }
}
