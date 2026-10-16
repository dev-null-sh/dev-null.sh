<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Jobs\ModerateCommit;
use App\Models\Commit;
use App\Models\CommitImage;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config(['services.openai.key' => 'sk-test']);
});

/**
 * @param  list<string>  $flagged
 * @param  array<string, float>  $scores
 * @return array{results: list<array{flagged: bool, categories: array<string, bool>, category_scores: array<string, float>}>}
 */
function moderationResponse(array $flagged = [], array $scores = []): array
{
    $categories = ['harassment', 'violence', 'sexual', 'sexual/minors'];

    return ['results' => [[
        'flagged' => $flagged !== [],
        'categories' => array_combine($categories, array_map(fn (string $category): bool => in_array($category, $flagged, true), $categories)),
        'category_scores' => array_combine($categories, array_map(fn (string $category): float => $scores[$category] ?? (in_array($category, $flagged, true) ? 0.9 : 0.0), $categories)),
    ]]];
}

/**
 * @return array{status: string, output: list<array{type: string, role: string, content: list<array<string, string>>}>}
 */
function visionResponse(bool|string $minors): array
{
    $content = is_bool($minors)
        ? ['type' => 'output_text', 'text' => json_encode(['minors' => $minors])]
        : ['type' => 'refusal', 'refusal' => $minors];

    return ['status' => 'completed', 'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [$content]]]];
}

it('stores that the ai would approve a commit it does not flag', function (): void {
    Http::fake(['api.openai.com/v1/moderations' => Http::response(moderationResponse())]);

    $commit = Commit::factory()->pending()->create(['body' => 'Shipped a new feature']);

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Approved)
        ->ai_categories->toBe([])
        ->status->toBe(CommitStatus::Pending);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer sk-test')
        && $request['model'] === 'omni-moderation-latest'
        && $request['input'] === [['type' => 'text', 'text' => 'Shipped a new feature']]);

    Http::assertSentCount(1);
});

it('stores that the ai would reject a commit it flags, and why', function (): void {
    Http::fake(['api.openai.com/v1/moderations' => Http::response(moderationResponse(['harassment', 'violence']))]);

    $commit = Commit::factory()->pending()->create();

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Rejected)
        ->ai_categories->toBe(['harassment', 'violence'])
        ->status->toBe(CommitStatus::Pending);
});

it('checks the images of a commit too, one per request', function (): void {
    Storage::fake();
    Storage::put('commit-images/first.webp', 'first');
    Storage::put('commit-images/second.webp', 'second');

    Http::fakeSequence('api.openai.com/v1/moderations')
        ->push(moderationResponse())
        ->push(moderationResponse(['sexual']));
    Http::fake(['api.openai.com/v1/responses' => Http::response(visionResponse(false))]);

    $commit = Commit::factory()->pending()->create(['body' => 'Look at this']);
    CommitImage::factory()->for($commit)->create(['path' => 'commit-images/first.webp', 'position' => 0]);
    CommitImage::factory()->for($commit)->create(['path' => 'commit-images/second.webp', 'position' => 1]);

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Rejected)
        ->ai_categories->toBe(['sexual']);

    $inputs = Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/moderations'))
        ->map(fn (array $pair): mixed => $pair[0]['input'])
        ->all();

    expect($inputs)->toBe([
        [
            ['type' => 'text', 'text' => 'Look at this'],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/webp;base64,'.base64_encode('first')]],
        ],
        [
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/webp;base64,'.base64_encode('second')]],
        ],
    ]);
});

it('flags categories scoring above the strict thresholds, even when openai does not flag them', function (): void {
    Http::fake(['api.openai.com/v1/moderations' => Http::response(moderationResponse(scores: [
        'harassment' => 0.2,
        'violence' => 0.19,
        'sexual/minors' => 0.01,
    ]))]);

    $commit = Commit::factory()->pending()->create();

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Rejected)
        ->ai_categories->toBe(['harassment', 'sexual/minors']);
});

it('rejects images that may show a minor', function (): void {
    Storage::fake();
    Storage::put('commit-images/first.webp', 'first');
    Storage::put('commit-images/second.webp', 'second');

    Http::fake([
        'api.openai.com/v1/moderations' => Http::response(moderationResponse()),
        'api.openai.com/v1/responses' => Http::response(visionResponse(true)),
    ]);

    $commit = Commit::factory()->pending()->create();
    CommitImage::factory()->for($commit)->create(['path' => 'commit-images/first.webp', 'position' => 0]);
    CommitImage::factory()->for($commit)->create(['path' => 'commit-images/second.webp', 'position' => 1]);

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Rejected)
        ->ai_categories->toBe(['minor-in-image']);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/responses')
        && $request['model'] === 'gpt-6-astra'
        && $request['input'][1]['content'] === [
            ['type' => 'input_image', 'image_url' => 'data:image/webp;base64,'.base64_encode('first')],
            ['type' => 'input_image', 'image_url' => 'data:image/webp;base64,'.base64_encode('second')],
        ]);
});

it('rejects images when the vision model gives no clear answer', function (array $response): void {
    Storage::fake();
    Storage::put('commit-images/first.webp', 'first');

    Http::fake([
        'api.openai.com/v1/moderations' => Http::response(moderationResponse()),
        'api.openai.com/v1/responses' => Http::response($response),
    ]);

    $commit = Commit::factory()->pending()->create();
    CommitImage::factory()->for($commit)->create(['path' => 'commit-images/first.webp']);

    dispatch_sync(new ModerateCommit($commit));

    expect($commit->refresh())
        ->ai_decision->toBe(CommitStatus::Rejected)
        ->ai_categories->toBe(['minor-in-image']);
})->with([
    'a refusal' => [visionResponse("I'm sorry, I cannot assist with that request.")],
    'unreadable text' => [['status' => 'incomplete', 'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => '{"min']]]]]],
    'no output' => [['status' => 'completed', 'output' => []]],
]);

it('skips commits that were moderated in the meantime', function (): void {
    Http::fake();

    $commit = Commit::factory()->create();

    dispatch_sync(new ModerateCommit($commit));

    Http::assertNothingSent();

    expect($commit->refresh()->ai_decision)->toBeNull();
});

it('skips commits that were deleted in the meantime', function (): void {
    Http::fake();

    $commit = Commit::factory()->pending()->create();
    $commit->delete();

    dispatch_sync(new ModerateCommit($commit));

    Http::assertNothingSent();
});

it('throws when the ai is unavailable, so the job is retried', function (): void {
    Http::fake(['api.openai.com/v1/moderations' => Http::response([], 503)]);

    $commit = Commit::factory()->pending()->create();

    expect(fn () => dispatch_sync(new ModerateCommit($commit)))->toThrow(RequestException::class)
        ->and($commit->refresh()->ai_decision)->toBeNull();
});
