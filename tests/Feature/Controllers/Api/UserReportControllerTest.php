<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('reports a person privately, with its reason and details', function (): void {
    $reported = User::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', $reported), [
        'reason' => 'child_safety',
        'details' => 'They asked my age.',
    ])->assertNoContent();

    expect(UserReport::query()->sole())
        ->user_id->toBe($reported->id)
        ->reporter_id->toBe($this->user->id)
        ->reason->toBe('child_safety')
        ->details->toBe('They asked my age.');
});

it('takes the reasons older app builds send, and the new one', function (string $reason): void {
    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', User::factory()->create()), ['reason' => $reason])
        ->assertNoContent();

    expect(UserReport::query()->sole())->reason->toBe($reason)->details->toBeNull();
})->with(['spam', 'harassment', 'unsafe', 'child_safety', 'other']);

it('refuses a missing or unknown reason, or details over 500 characters', function (array $data, string $field): void {
    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', User::factory()->create()), $data)
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($field);

    expect(UserReport::query()->count())->toBe(0);
})->with([
    'no reason' => [['details' => 'Spam.'], 'reason'],
    'unknown reason' => [['reason' => 'approve'], 'reason'],
    'details too long' => [['reason' => 'spam', 'details' => Str::repeat('x', 501)], 'details'],
]);

it('may not report themselves, with a code', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', $this->user), ['reason' => 'spam'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'You cannot report yourself.', 'code' => 'own_profile']);

    expect(UserReport::query()->count())->toBe(0);
});

it('gives the same answer for someone who does not exist and someone who blocked the user', function (): void {
    $blocker = User::factory()->create();
    $blocker->blocking()->attach($this->user);
    $answer = ['message' => "This profile isn't available.", 'code' => 'user_unavailable'];

    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', $blocker), ['reason' => 'spam'])
        ->assertForbidden()
        ->assertExactJson($answer);
    signedApiRequest($this->keyPair, 'POST', '/api/v1/users/nobody-has-this-handle/reports', ['reason' => 'spam'])
        ->assertForbidden()
        ->assertExactJson($answer);

    expect(UserReport::query()->count())->toBe(0);
});

it('still lets the user report someone they blocked', function (): void {
    $blocked = User::factory()->create();
    $this->user->blocking()->attach($blocked);

    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', $blocked), ['reason' => 'harassment'])
        ->assertNoContent();

    expect(UserReport::query()->sole()->user_id)->toBe($blocked->id);
});

it('keeps the first report while it is open', function (): void {
    $reported = User::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', $reported), ['reason' => 'spam'])->assertNoContent();
    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', $reported), ['reason' => 'other'])->assertNoContent();

    expect(UserReport::query()->sole()->reason)->toBe('spam');
});

it('throttles reports per reporter', function (): void {
    $reported = User::factory()->create();

    foreach (range(1, 5) as $ignored) {
        signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', $reported), ['reason' => 'spam'])->assertNoContent();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', $reported), ['reason' => 'spam'])->assertTooManyRequests();
});

it('needs a signature', function (): void {
    $this->postJson(route('api-user-report.store', User::factory()->create()), ['reason' => 'spam'])->assertUnauthorized();
});
