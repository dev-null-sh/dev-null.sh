<?php

declare(strict_types=1);

use App\Actions\UpdateMentionedOrganizations;
use App\Enums\ProfileType;
use App\Jobs\LookUpUserOrganizations;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('looks the organizations the bio and company mention up, but not the users', function (): void {
    Http::fake([
        'api.github.com/users/laravel' => Http::response(['login' => 'laravel', 'id' => 958072, 'type' => 'Organization', 'name' => 'Laravel', 'avatar_url' => null, 'bio' => null, 'location' => null, 'blog' => '', 'company' => null]),
        'api.github.com/users/*' => Http::response(status: 404),
    ]);

    User::factory()->create(['handle' => 'taylorotwell']);
    $user = User::factory()->create(['bio' => 'Thanks @TaylorOtwell and @nobody', 'company' => '@laravel']);

    dispatch_sync(new LookUpUserOrganizations($user));

    expect(Profile::query()->where('type', ProfileType::Organization)->pluck('handle')->all())->toBe(['laravel']);
    Http::assertSentCount(2);
    Http::assertNotSent(fn ($request): bool => str_contains((string) $request->url(), 'taylorotwell'));
});

it('runs again once the rate limit resets when it cut the lookups short', function (): void {
    Http::fake();

    $resetsAt = now()->addMinutes(30)->startOfSecond();
    Cache::put('github-organizations:paused', $resetsAt->getTimestamp(), $resetsAt);
    $job = new LookUpUserOrganizations(User::factory()->create(['bio' => 'Building @laravel']))->withFakeQueueInteractions();

    $job->handle(resolve(UpdateMentionedOrganizations::class));

    Http::assertNothingSent();
    $job->assertReleased($resetsAt);
});

it('is done once it looked every handle up', function (): void {
    Http::fake(['api.github.com/users/*' => Http::response(status: 404)]);

    $job = new LookUpUserOrganizations(User::factory()->create(['bio' => 'Building @laravel']))->withFakeQueueInteractions();

    $job->handle(resolve(UpdateMentionedOrganizations::class));

    $job->assertNotReleased();
});

it('is queued only for a bio or company that mentions someone', function (?string $bio, ?string $company, bool $queued): void {
    Queue::fake();

    $user = User::factory()->create(['bio' => $bio, 'company' => $company]);

    expect(LookUpUserOrganizations::dispatchFor($user))->toBe($queued);
    $queued
        ? Queue::assertPushed(LookUpUserOrganizations::class, fn (LookUpUserOrganizations $job): bool => $job->user->is($user))
        : Queue::assertNothingPushed();
})->with([
    'a bio mention' => ['Building @laravel', null, true],
    'a company mention' => [null, '@laravel', true],
    'no mention' => ['Building things', 'Acme', false],
    'nothing' => [null, null, false],
]);
