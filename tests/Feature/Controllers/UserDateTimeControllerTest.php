<?php

declare(strict_types=1);

use App\Enums\DateFormat;
use App\Enums\TimeFormat;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('uses browser defaults for a newly created user before reloading it', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('user-profile.edit'))->assertInertia(fn (Assert $page): Assert => $page
        ->where('auth.dateTime.dateFormat', null)
        ->where('auth.dateTime.timeFormat', null));
});

it('saves date and time preferences without changing the timezone', function (): void {
    $user = User::factory()->create(['timezone' => 'Europe/Budapest']);

    $this->actingAs($user)->fromRoute('appearance.edit')
        ->put(route('user-date-time.update'), [
            'timezone' => 'America/Chicago',
            'date_format' => 'day_first',
            'time_format' => '24h',
        ])->assertRedirectToRoute('appearance.edit')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Date and time preferences saved.']);

    expect($user->refresh())
        ->timezone->toBe('Europe/Budapest')
        ->date_format->toBe(DateFormat::DayFirst)
        ->time_format->toBe(TimeFormat::TwentyFourHour);
});

it('clears overrides back to browser defaults', function (mixed $value): void {
    $user = User::factory()->create(['timezone' => 'UTC', 'date_format' => DateFormat::MonthFirst, 'time_format' => TimeFormat::TwelveHour]);

    $this->actingAs($user)->put(route('user-date-time.update'), [
        'date_format' => $value, 'time_format' => $value,
    ])->assertSessionHasNoErrors();

    expect($user->refresh())->timezone->toBe('UTC')->date_format->toBeNull()->time_format->toBeNull();
})->with([null, '']);

it('validates all preferences without partially saving', function (array $overrides, string $field): void {
    $user = User::factory()->create(['date_format' => DateFormat::DayFirst]);

    $this->actingAs($user)->put(route('user-date-time.update'), [
        'date_format' => 'month_first', 'time_format' => '12h', ...$overrides,
    ])->assertSessionHasErrors($field);

    expect($user->refresh()->date_format)->toBe(DateFormat::DayFirst);
})->with([
    [['date_format' => 'iso'], 'date_format'],
    [['time_format' => '25h'], 'time_format'],
]);

it('requires all preference keys', function (): void {
    $this->actingAs(User::factory()->create())->put(route('user-date-time.update'))
        ->assertSessionHasErrors(['date_format', 'time_format']);
});

it('only shares display preferences with their owner', function (): void {
    $user = User::factory()->create(['date_format' => DateFormat::DayFirst, 'time_format' => TimeFormat::TwentyFourHour]);
    expect($user->toArray())->not->toHaveKeys(['date_format', 'time_format']);

    $this->actingAs($user)->get(route('user-profile.edit'))->assertInertia(fn (Assert $page): Assert => $page
        ->where('auth.dateTime.dateFormat', 'day_first')
        ->where('auth.dateTime.timeFormat', '24h'));
});

it('requires authentication', function (): void {
    $this->put(route('user-date-time.update'))->assertRedirectToRoute('home');
});
