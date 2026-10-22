<?php

declare(strict_types=1);

use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\User;
use App\Rules\CommitBodyLength;
use App\Services\Urls;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('previews a draft the way the commit it becomes is shown', function (string $body): void {
    $preview = signedApiRequest($this->keyPair, 'POST', route('api-commit-preview.store'), ['body' => $body]);

    $commit = signedApiRequest($this->keyPair, 'POST', route('api-commit.store'), ['body' => $body])->json('data');

    $preview->assertOk()
        ->assertExactJson(['data' => [
            'body' => $commit['body'],
            'body_segments' => $commit['body_segments'],
            'preview_segments' => $commit['preview_segments'],
            'mentions' => $commit['mentions'],
        ]]);
})->with([
    'code, mentions and emoji' => "  shipped :rocket: with @nunomaduro\n```php\n\$name = 'devnull';\n```\nrun `composer test` :fire:  ",
    'runs of blank lines' => "Shipped it.\n\n\n\nFinally.",
    'too long to show whole in a list' => implode("\n", array_fill(0, 12, 'One more line of the changelog.')),
]);

it('previews publishable text longer than 4000 raw characters when it contains links', function (): void {
    Queue::fake([UnfurlLink::class]);
    $body = implode(' ', array_fill(0, 3, mb_str_pad('https://example.com/', 1400, 'a')));

    signedApiRequest($this->keyPair, 'POST', route('api-commit-preview.store'), ['body' => $body])
        ->assertOk()
        ->assertJsonPath('data.body', $body);

    expect(Commit::query()->exists())->toBeFalse();
    Queue::assertNothingPushed();

    signedApiRequest($this->keyPair, 'POST', route('api-commit.store'), ['body' => $body])
        ->assertCreated()
        ->assertJsonPath('data.body', $body);
});

it('folds a long draft as a list would, and leaves a short one whole', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-commit-preview.store'), ['body' => str_repeat('word ', 200)])
        ->assertOk()
        ->assertJsonPath('data.preview_segments.0.type', 'text');

    signedApiRequest($this->keyPair, 'POST', route('api-commit-preview.store'), ['body' => 'It was DNS.'])
        ->assertOk()
        ->assertJsonPath('data.body_segments', [['type' => 'text', 'text' => 'It was DNS.']])
        ->assertJsonPath('data.preview_segments', null)
        ->assertJsonPath('data.mentions', []);
});

it('previews drafts without committing them or spending the commit budget', function (): void {
    foreach (range(1, 11) as $draft) {
        signedApiRequest($this->keyPair, 'POST', route('api-commit-preview.store'), ['body' => "Draft {$draft}"])->assertOk();
    }

    expect(Commit::query()->exists())->toBeFalse();

    signedApiRequest($this->keyPair, 'POST', route('api-commit.store'), ['body' => 'Shipped it.'])->assertCreated();
});

it('requires a body the composer could commit', function (array $data, string $message): void {
    signedApiRequest($this->keyPair, 'POST', route('api-commit-preview.store'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body' => $message]);
})->with([
    'missing' => [[], 'The body field is required.'],
    'blank' => [['body' => "  \n  "], 'The body field is required.'],
    'too long' => [['body' => str_repeat('a', Commit::MAX_BODY_LENGTH + 1)], 'The body field must not be greater than '.Commit::MAX_BODY_LENGTH.' characters.'],
    'oversized link' => [['body' => mb_str_pad('https://example.com/', Urls::MAX_LENGTH + 1, 'a')], 'Each link must not be greater than '.Urls::MAX_LENGTH.' characters.'],
    'raw body too long' => [['body' => implode(' ', array_fill(0, 20, mb_str_pad('https://example.com/', Urls::MAX_LENGTH, 'a')))], 'The body field must not be greater than '.CommitBodyLength::MAX_RAW_LENGTH.' characters.'],
]);

it('requires a signed request', function (): void {
    $this->postJson(route('api-commit-preview.store'), ['body' => 'It was DNS.'])
        ->assertUnauthorized();
});
