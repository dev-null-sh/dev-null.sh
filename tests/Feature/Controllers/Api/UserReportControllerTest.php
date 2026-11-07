<?php

declare(strict_types=1);

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('reports a person privately, keeping the first report, and tells moderators', function (): void {
    Event::fake([UsersUpdated::class]);
    config(['app.admins' => ['moderator-demo']]);
    $admin = User::factory()->create(['handle' => 'moderator-demo']);
    $reported = User::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', $reported), ['reason' => 'harassment', 'details' => 'Hateful bio.'])
        ->assertNoContent();
    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', $reported), ['reason' => 'spam'])
        ->assertNoContent();

    $report = UserReport::query()->sole();

    expect($report->reporter_id)->toBe($this->user->id)
        ->and($report->user_id)->toBe($reported->id)
        ->and($report->reason)->toBe('harassment')
        ->and($report->details)->toBe('Hateful bio.');

    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Moderation
        && $event->userIds === [$admin->id]
        && $event->data === ['user_reports' => [$report->id]]);
});

it('refuses a missing or unknown reason, or details over 500 characters', function (array $data, string $field): void {
    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', User::factory()->create()), $data)
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($field);

    expect(UserReport::query()->count())->toBe(0);
})->with([
    'no reason' => [[], 'reason'],
    'unknown reason' => [['reason' => 'approve'], 'reason'],
    'details too long' => [['reason' => 'spam', 'details' => Str::repeat('x', 501)], 'details'],
]);

it('may not report themselves, with a code', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', $this->user), ['reason' => 'spam'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'You cannot report yourself.', 'code' => 'own_profile']);

    expect(UserReport::query()->count())->toBe(0);
});

it('answers a handle nobody has as not found', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-user-report.store', ['user' => 'nobody-here']), ['reason' => 'spam'])
        ->assertNotFound();
});
