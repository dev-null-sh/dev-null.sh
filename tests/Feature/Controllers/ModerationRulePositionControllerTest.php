<?php

declare(strict_types=1);

use App\Models\ModerationRule;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('moves a rule up or down the list', function (string $name, string $direction, array $order): void {
    $admin = User::factory()->create(['username' => 'admin']);
    ModerationRule::factory()->create(['name' => 'Spam', 'position' => 0]);
    ModerationRule::factory()->create(['name' => 'Scams', 'position' => 1]);
    ModerationRule::factory()->create(['name' => 'Hate', 'position' => 5]);

    $rule = ModerationRule::query()->where('name', $name)->sole();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation-rule.index')
        ->put(route('moderation-rule-position.update', $rule), ['direction' => $direction]);

    $response->assertRedirectToRoute('moderation-rule.index');

    expect(ModerationRule::query()->ordered()->pluck('name')->all())->toBe($order);
})->with([
    'up' => ['Scams', 'up', ['Scams', 'Spam', 'Hate']],
    'down' => ['Scams', 'down', ['Spam', 'Hate', 'Scams']],
    'up from the top' => ['Spam', 'up', ['Spam', 'Scams', 'Hate']],
    'down from the bottom' => ['Hate', 'down', ['Spam', 'Scams', 'Hate']],
]);

it('renumbers the rules when moving one', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    ModerationRule::factory()->create(['position' => 3]);
    $last = ModerationRule::factory()->create(['position' => 9]);

    $this->actingAs($admin)->put(route('moderation-rule-position.update', $last), ['direction' => 'up']);

    expect(ModerationRule::query()->ordered()->pluck('position')->all())->toBe([0, 1])
        ->and($last->refresh()->position)->toBe(0);
});

it('validates the direction', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $rule = ModerationRule::factory()->create();

    $response = $this->actingAs($admin)->put(route('moderation-rule-position.update', $rule), ['direction' => 'sideways']);

    $response->assertSessionHasErrors('direction');
});
