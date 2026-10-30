<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\TranslationProvider;
use App\Enums\CommitStatus;
use App\Exceptions\CommitProcessingFailed;
use App\Exceptions\InvalidTranslation;
use App\Models\Commit;
use App\Services\CommitTranslations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final readonly class CreateCommitTranslation
{
    public function __construct(private CommitTranslations $translations, private TranslationProvider $translator)
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

            $reason = 'provider_request';

            try {
                ['source_language' => $source, 'body' => $body] = $this->translator->translate($commit->body, $language);

                $reason = match (true) {
                    ! in_array($source, [...$this->translations->languages(), 'und'], true) => 'unsupported_source_language',
                    $language !== null && $source !== 'und' && blank($body) => 'empty_translation',
                    $language !== null && $source !== 'und' && mb_strlen($body) > 16000 => 'translation_too_long',
                    default => null,
                };

                if ($reason !== null) {
                    throw new RuntimeException('Invalid translation response.');
                }
            } catch (InvalidTranslation $invalid) {
                throw new CommitProcessingFailed('Translation', $commit->id, new RuntimeException($invalid->getMessage()), $invalid->reason);
            } catch (Throwable $throwable) {
                throw new CommitProcessingFailed('Translation', $commit->id, $throwable, $reason);
            }

            DB::transaction(function () use ($commit, $language, $key, $cached, $source, $body): void {
                $current = Commit::query()->lockForUpdate()->find($commit->id);

                if (! $current instanceof Commit || ! in_array($current->status, [CommitStatus::Approved, CommitStatus::Pending], true) || $current->body !== $commit->body) {
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
