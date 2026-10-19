<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\CommitTranslator;
use App\Enums\CommitStatus;
use App\Exceptions\CommitProcessingFailed;
use App\Models\Commit;
use App\Services\CommitTranslations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

final readonly class CreateCommitTranslation
{
    public function __construct(private CommitTranslations $translations)
    {
        //
    }

    public function handle(Commit $commit, ?string $language = null): void
    {
        $key = $this->translations->key($commit);

        Cache::lock($key.':lock', 90)->block(35, function () use ($commit, $language, $key): void {
            $cached = $this->translations->cached($commit);

            if ($cached !== null && ($language === null || isset($cached['translations'][$language]) || in_array($cached['source_language'], [$language, 'und'], true))) {
                return;
            }

            try {
                $response = new CommitTranslator($language)->prompt($commit->body);
                assert($response instanceof StructuredAgentResponse);

                $source = $response['source_language'];
                $body = $response['body'];

                if (! is_string($source) || ! in_array($source, [...$this->translations->languages(), 'und'], true)
                    || ! is_string($body) || ($language !== null && $source !== 'und' && (blank($body) || mb_strlen($body) > 16000))) {
                    throw new RuntimeException('Invalid translation response.');
                }
            } catch (Throwable $throwable) {
                throw new CommitProcessingFailed('Translation', $commit->id, $throwable);
            }

            DB::transaction(function () use ($commit, $language, $key, $cached, $source, $body): void {
                $current = Commit::query()->lockForUpdate()->find($commit->id);

                if (! $current instanceof Commit || $current->status !== CommitStatus::Approved || $current->body !== $commit->body) {
                    throw new RuntimeException('Commit changed during translation.');
                }

                $translations = $cached['translations'] ?? [];

                if ($language !== null && $source !== $language && $source !== 'und') {
                    $translations[$language] = $body;
                }

                $expiresAt = $cached['expires_at'] ?? now()->getTimestamp() + CommitTranslations::TTL_SECONDS;

                Cache::memo()->put($key, ['source_language' => $source, 'translations' => $translations, 'expires_at' => $expiresAt], max(1, $expiresAt - now()->getTimestamp()));
            });
        });
    }
}
