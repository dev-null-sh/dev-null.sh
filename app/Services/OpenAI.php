<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class OpenAI
{
    private const string MODERATION_MODEL = 'omni-moderation-latest';

    private const string VISION_MODEL = 'gpt-6-astra';

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

    private const string MINORS_INSTRUCTIONS = <<<'TEXT'
        You review images uploaded to devnull, a social network for developers, before they are published.
        Answer whether any person in the images is, or could plausibly be, under 18 years old. That includes babies,
        children and teenagers, whether in photos, drawings, renders or any other depiction. When unsure, answer true.
        TEXT;

    public function request(): PendingRequest
    {
        return Http::baseUrl('https://api.openai.com/v1')
            ->withToken(config()->string('services.openai.key'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(15);
    }

    /**
     * The categories the content is flagged for, if any: those OpenAI flags, and those scoring above our own, stricter
     * thresholds. The text goes along with the first image, and every other image is checked on its own, as a single
     * request is only documented to take one image.
     *
     * @param  list<string>  $imageDataUrls
     * @return list<string>
     */
    public function moderate(string $text, array $imageDataUrls = []): array
    {
        $inputs = [[['type' => 'text', 'text' => $text]]];

        foreach ($imageDataUrls as $index => $imageDataUrl) {
            $image = ['type' => 'image_url', 'image_url' => ['url' => $imageDataUrl]];

            if ($index === 0) {
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
                    if ($isFlagged || ($result['category_scores'][$category] ?? 0.0) >= (self::THRESHOLDS[$category] ?? self::STRICT_THRESHOLD)) {
                        $categories[] = $category;
                    }
                }
            }
        }

        return array_values(array_unique($categories));
    }

    /**
     * Whether a minor may appear in any of the images. The moderation model can't tell, so a vision model is asked
     * instead. Anything but a clear no, such as a refusal or an answer that can't be read, counts as a yes.
     *
     * @param  list<string>  $imageDataUrls
     */
    public function showsMinors(array $imageDataUrls): bool
    {
        /** @var list<array{type: string, content?: list<array{type: string, text?: string}>}> $output */
        $output = $this->request()
            ->timeout(60)
            ->post('responses', [
                'model' => self::VISION_MODEL,
                'input' => [
                    ['role' => 'system', 'content' => self::MINORS_INSTRUCTIONS],
                    ['role' => 'user', 'content' => array_map(
                        fn (string $imageDataUrl): array => ['type' => 'input_image', 'image_url' => $imageDataUrl],
                        $imageDataUrls,
                    )],
                ],
                'text' => ['format' => [
                    'type' => 'json_schema',
                    'name' => 'minors',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => ['minors' => ['type' => 'boolean']],
                        'required' => ['minors'],
                        'additionalProperties' => false,
                    ],
                ]],
            ])
            ->throw()
            ->json('output', []);

        foreach ($output as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if ($content['type'] === 'output_text') {
                    $answer = json_decode($content['text'] ?? '', true);

                    return ! is_array($answer) || $answer['minors'] !== false;
                }
            }
        }

        return true;
    }
}
