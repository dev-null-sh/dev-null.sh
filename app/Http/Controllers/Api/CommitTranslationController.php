<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateCommitTranslation;
use App\Exceptions\ApiException;
use App\Exceptions\CommitProcessingFailed;
use App\Http\Requests\CreateApiCommitTranslationRequest;
use App\Http\Resources\CommitResource;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitPageQuery;
use App\Services\CommitTranslations;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Throwable;

final readonly class CommitTranslationController
{
    /**
     * Translates a published commit the way Translate does on the web, and answers with the commit as its page shows
     * it. The translation is for the requested language, which may differ from the language saved on the account.
     * The original post is unchanged. A provider failure keeps the cache empty and never returns the provider's text.
     */
    public function store(CreateApiCommitTranslationRequest $request, #[CurrentUser] User $user, Commit $commit, CreateCommitTranslation $action, CommitTranslations $translations, CommitPageQuery $page): JsonResponse
    {
        $language = $request->string('language')->value();

        try {
            $action->handle($commit, $language);
        } catch (Throwable $throwable) {
            report($throwable instanceof CommitProcessingFailed ? $throwable : new CommitProcessingFailed('Translation', $commit->id, $throwable));

            $message = $throwable instanceof CommitProcessingFailed && $throwable->reason === 'unsupported_source_language'
                ? 'The translation service returned an unsupported source language. Try again.'
                : 'Could not translate this commit. Try again.';

            return ApiException::translationFailed($message)
                ->render()
                ->header('Cache-Control', 'no-store, private');
        }

        $commit = $page->get($commit, $user);
        $commit->setAttribute('translation', $translations->display($commit, $language));

        return CommitResource::make($commit)
            ->response()
            ->header('Cache-Control', 'no-store, private');
    }
}
