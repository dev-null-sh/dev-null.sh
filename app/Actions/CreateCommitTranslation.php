<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\ModerationProvider;
use App\Contracts\Services\TranslationProvider;
use App\Enums\CommitStatus;
use App\Enums\CommitUpdate;
use App\Enums\UserUpdate;
use App\Events\CommitsUpdated;
use App\Events\UsersUpdated;
use App\Exceptions\CommitProcessingFailed;
use App\Exceptions\InvalidTranslation;
use App\Models\Commit;
use App\Services\CommitTranslations;
use App\Services\EncodedText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final readonly class CreateCommitTranslation
{
    public function __construct(private CommitTranslations $translations, private TranslationProvider $translator, private EncodedText $encodedText, private ModerationProvider $moderation)
    {
        //
    }

    public function handle(Commit $commit, ?string $language = null): void
    {
        $key = $this->translations->key($commit);

        Cache::lock($key.':lock', 180)->block(5, function () use ($commit, $language, $key): void {
            $cached = $this->translations->cached($commit);

            if ($cached !== null && ($language === null || isset($cached['translations'][$language]) || in_array($cached['source_language'], [$language, 'und'], true))) {
                return;
            }

            $reason = 'provider_request';

            try {
                $encoded = $this->encodedText->decode($commit->body);

                if ($encoded !== null && $language !== null) {
                    // Older posts and trusted authors may never have had their decoded contents moderated.
                    $reason = 'encoded_text_moderation';

                    if ($this->moderation->moderate($encoded['body']) !== [] || $this->moderation->isHarmful($encoded['body'])) {
                        throw new RuntimeException('Unsafe encoded text.');
                    }

                    $reason = 'provider_request';
                }

                ['source_language' => $source, 'body' => $body] = $encoded !== null && $language === null
                    ? ['source_language' => 'und', 'body' => '']
                    : $this->translator->translate($encoded['body'] ?? $commit->body, $language);

                $reason = match (true) {
                    preg_match('/^[a-z]{2,3}\z/', $source) !== 1 => 'unsupported_source_language',
                    $language !== null && $source !== 'und' && blank($body) => 'empty_translation',
                    $language !== null && ($source !== 'und' || ($encoded !== null && filled($body))) && mb_strlen($body) > 16000 => 'translation_too_long',
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

            if ($encoded !== null) {
                // Decoding is useful even when the decoded text already matches the reader's language or is just emoji.
                $body = $source === 'und' && blank($body) ? $encoded['body'] : $body;
                $source = $encoded['source_language'];
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

                Cache::memo()->put($key, ['source_language' => $source, 'translations' => $translations, 'expires_at' => $expiresAt, 'translated_at' => now()->getTimestampMs()], max(1, $expiresAt - now()->getTimestamp()));

                // Each reader shows it in their own language, so they read it again.
                $current->status === CommitStatus::Approved
                    ? event(new CommitsUpdated([$current], CommitUpdate::Translation))
                    : event(new UsersUpdated(array_filter([$current->user_id]), UserUpdate::Commits));
            });
        });
    }
}
