<?php

declare(strict_types=1);

use App\Models\ModerationRule;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('moves a rule to another place in the list', function (string $name, int $position, array $order): void {
    $admin = User::factory()->create(['username' => 'admin']);
    ModerationRule::factory()->create(['name' => 'Spam', 'position' => 0]);
    ModerationRule::factory()->create(['name' => 'Scams', 'position' => 1]);
    ModerationRule::factory()->create(['name' => 'Hate', 'position' => 5]);

    $rule = ModerationRule::query()->where('name', $name)->sole();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation-rule.index')
        ->put(route('moderation-rule-position.update', $rule), ['position' => $position]);

    $response->assertRedirectToRoute('moderation-rule.index');

    expect(ModerationRule::query()->ordered()->pluck('name')->all())->toBe($order)
        ->and(ModerationRule::query()->ordered()->pluck('position')->all())->toBe([0, 1, 2]);
})->with([
    'up' => ['Scams', 0, ['Scams', 'Spam', 'Hate']],
    'down' => ['Scams', 2, ['Spam', 'Hate', 'Scams']],
    'where it already is' => ['Spam', 0, ['Spam', 'Scams', 'Hate']],
    'past the end' => ['Spam', 9, ['Scams', 'Hate', 'Spam']],
]);

it('validates the position', function (mixed $position): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $rule = ModerationRule::factory()->create();

    $response = $this->actingAs($admin)->put(route('moderation-rule-position.update', $rule), ['position' => $position]);

    $response->assertSessionHasErrors('position');
})->with([-1, 'up', null, 256]);
