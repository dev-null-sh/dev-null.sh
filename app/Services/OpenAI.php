<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class OpenAI
{
    private const string MODERATION_MODEL = 'omni-moderation-latest';

    private const string REVIEW_MODEL = 'gpt-6-astra';

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

    private const string HARMFUL_INSTRUCTIONS = <<<'TEXT'
        You review posts on devnull, a social network for developers, before they are published.
        Answer whether the post is harmful: a threat, including claims of having a bomb, weapon or explosive, or of
        planning an attack, even as a joke; violence, incitement or glorification of it; hate speech or harassment of a
        person or group; sexual content; encouragement of self-harm; or illegal activity. Developer jargon such as
        "kill the process", "nuke the database" or "this release is the bomb" is not harmful. Disliking a technology
        is not harmful either. When unsure, answer true.
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
     * The categories the content is flagged for, if any, each with its highest score: those OpenAI flags, and those
     * scoring above our own, stricter thresholds. The text goes along with the first image, and every other image is
     * checked on its own, as a single request is only documented to take one image.
     *
     * @param  list<string>  $imageDataUrls
     * @return array<string, float>
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
     * Whether a minor may appear in any of the images. The moderation model can't tell, so a reasoning model is asked
     * instead. Anything but a clear no, such as a refusal or an answer that can't be read, counts as a yes.
     *
     * @param  list<string>  $imageDataUrls
     */
    public function showsMinors(array $imageDataUrls): bool
    {
        return $this->ask('minors', self::MINORS_INSTRUCTIONS, array_map(
            fn (string $imageDataUrl): array => ['type' => 'input_image', 'image_url' => $imageDataUrl],
            $imageDataUrls,
        ));
    }

    /**
     * Whether the text is harmful. The moderation model misses threats that are implied rather than spelled out, such
     * as claiming to have a bomb, so a reasoning model is asked too. Anything but a clear no counts as a yes.
     */
    public function isHarmful(string $text): bool
    {
        return $this->ask('harmful', self::HARMFUL_INSTRUCTIONS, [['type' => 'input_text', 'text' => $text]]);
    }

    /**
     * Asks the reasoning model a yes or no question about the content. Anything but a clear no, such as a refusal or an
     * answer that can't be read, counts as a yes.
     *
     * @param  list<array<string, string>>  $content
     */
    private function ask(string $question, string $instructions, array $content): bool
    {
        /** @var list<array{type: string, content?: list<array{type: string, text?: string}>}> $output */
        $output = $this->request()
            ->timeout(60)
            ->post('responses', [
                'model' => self::REVIEW_MODEL,
                'input' => [
                    ['role' => 'system', 'content' => $instructions],
                    ['role' => 'user', 'content' => $content],
                ],
                'text' => ['format' => [
                    'type' => 'json_schema',
                    'name' => $question,
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [$question => ['type' => 'boolean']],
                        'required' => [$question],
                        'additionalProperties' => false,
                    ],
                ]],
            ])
            ->throw()
            ->json('output', []);

        foreach ($output as $item) {
            foreach ($item['content'] ?? [] as $part) {
                if ($part['type'] === 'output_text') {
                    $answer = json_decode($part['text'] ?? '', true);

                    return ! is_array($answer) || ($answer[$question] ?? null) !== false;
                }
            }
        }

        return true;
    }
}
