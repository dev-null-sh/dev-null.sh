<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateCommitTranslation;
use App\Exceptions\CommitProcessingFailed;
use App\Http\Requests\CreateCommitTranslationRequest;
use App\Models\Commit;
use App\Services\CommitTranslations;
use Illuminate\Http\JsonResponse;
use Throwable;

final readonly class CommitTranslationController
{
    public function store(CreateCommitTranslationRequest $request, Commit $commit, CreateCommitTranslation $action, CommitTranslations $translations): JsonResponse
    {
        try {
            $action->handle($commit, $request->string('language')->value());
        } catch (Throwable $throwable) {
            report($throwable instanceof CommitProcessingFailed ? $throwable : new CommitProcessingFailed('Translation', $commit->id, $throwable));

            return response()->json(['message' => 'Could not translate this commit. Try again.'], 503)
                ->header('Cache-Control', 'no-store, private');
        }

        return response()->json(['translation' => $translations->display($commit, $request->string('language')->value())])
            ->header('Cache-Control', 'no-store, private');
    }
}
