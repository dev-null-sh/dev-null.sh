<?php

declare(strict_types=1);

namespace App\Services\ModerationProviders;

use App\Ai\Agents\HarmReviewer;
use App\Contracts\Services\ModerationProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class OpenAIModerationProvider implements ModerationProvider
{
    private const string MODERATION_MODEL = 'omni-moderation-latest';

    /**
     * Far stricter than OpenAI's own cut-off: any category scoring this much is flagged.
     */
    private const float STRICT_THRESHOLD = 0.2;

    /**
     * @var array<string, float>
     */
    private const array THRESHOLDS = [
        'sexual/minors' => 0.01,
    ];

    /**
     * The categories the content is flagged for, if any, each with its highest score: those OpenAI flags, and those
     * scoring above our own, stricter thresholds. The text, if any, goes along with the first image, and every other
     * image is checked on its own, as a single request is only documented to take one image.
     *
     * @param  list<string>  $imageDataUrls
     * @return array<string, float>
     */
    public function moderate(string $text, array $imageDataUrls = []): array
    {
        $inputs = $text === '' ? [] : [[['type' => 'text', 'text' => $text]]];

        foreach ($imageDataUrls as $index => $imageDataUrl) {
            $image = ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]];

            if ($index === 0 && $inputs !== []) {
                $inputs[0][] = $image;
            } else {
                $inputs[] = [$image];
            }
        }

        $categories = [];

        foreach ($inputs as $input) {
            /** @var list<array{categories: array<string, bool>, category_scores: array<string, float>}> $results */
            $results = $this->request()
                ->post('moderations', ['model' => self::MODERATION_MODEL, 'input' => $input])
                ->throw()
                ->json('results');

            foreach ($results as $result) {
                foreach ($result['categories'] as $category => $isFlagged) {
                    $score = $result['category_scores'][$category] ?? 0.0;

                    if ($isFlagged || $score >= (self::THRESHOLDS[$category] ?? self::STRICT_THRESHOLD)) {
                        $categories[$category] = max($categories[$category] ?? 0.0, $score);
                    }
                }
            }
        }

        return $categories;
    }

    /**
     * Whether the text is harmful. The moderation model misses threats that are implied rather than spelled out, such
     * as claiming to have a bomb, so a reasoning model is asked too. Anything but a clear no counts as a yes.
     */
    public function isHarmful(string $text): bool
    {
        $answer = new HarmReviewer()->prompt($text);

        return ($answer['harmful'] ?? null) !== false;
    }

    private function request(): PendingRequest
    {
        $url = config('ai.providers.openai.url');
        assert(is_string($url));

        $key = config('ai.providers.openai.key');
        assert(is_string($key));

        return Http::baseUrl($url)
            ->withToken($key)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(15);
    }
}
