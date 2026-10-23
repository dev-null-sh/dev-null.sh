<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateLinkPreviewJob;
use App\Dto\LinkPreviewMetadata;
use App\Enums\EmbedKind;
use App\Http\Requests\ShowDraftLinkPreviewRequest;
use App\Models\LinkPreview;
use App\Models\User;
use App\Services\Embeds;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Uri\WhatWg\Url;

final readonly class DraftLinkPreviewController
{
    public const int READS_PER_MINUTE = 120;

    private const int UNFURLS_PER_MINUTE = 10;

    public function __construct(private CreateLinkPreviewJob $createLinkPreviewJob)
    {
        //
    }

    public function show(ShowDraftLinkPreviewRequest $request, Embeds $embeds): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $url = $request->string('url')->value();

        Log::info('Draft link preview requested', [
            'user_id' => $user->id,
            'host' => Url::parse($url)?->getAsciiHost(),
        ]);

        if ($embeds->for($url)?->kind === EmbedKind::Gif) {
            return $this->response($this->preview($url, new LinkPreviewMetadata));
        }

        $key = "draft-link-unfurls:{$user->id}";

        return $this->response($this->cachedPreview($url, $key));
    }

    private function cachedPreview(string $url, string $key): LinkPreview
    {
        $cached = LinkPreview::query()->where('url_hash', hash('sha256', $url))->first();

        if ($cached instanceof LinkPreview) {
            return $cached;
        }

        if (RateLimiter::tooManyAttempts($key, self::UNFURLS_PER_MINUTE)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($key));
        }

        RateLimiter::increment($key, CarbonInterface::SECONDS_PER_MINUTE);

        return $this->createPreview($url);
    }

    private function createPreview(string $url): LinkPreview
    {
        return DB::transaction(function () use ($url): LinkPreview {
            $preview = LinkPreview::query()->firstOrCreate(
                ['url_hash' => hash('sha256', $url)],
                ['url' => $url, ...(new LinkPreviewMetadata)->toArray()],
            );

            if ($preview->wasRecentlyCreated) {
                DB::afterCommit(function () use ($preview): void {
                    $this->createLinkPreviewJob->handle($preview);
                });
            }

            return $preview;
        });
    }

    private function response(LinkPreview $preview): JsonResponse
    {
        return response()->json([
            'link_preview' => $preview,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function preview(string $url, LinkPreviewMetadata $metadata): LinkPreview
    {
        return new LinkPreview([
            'url' => $url,
            ...$metadata->toArray(),
            'fetched_at' => now(),
        ]);
    }
}
