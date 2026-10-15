<?php

declare(strict_types=1);

use App\Models\ModerationRule;
use App\Models\User;

it('adds the starter rules', function (): void {
    config(['app.admins' => ['admin']]);

    $response = $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->fromRoute('moderation-rule.index')
        ->post(route('starter-moderation-rule.store'));

    $response->assertRedirectToRoute('moderation-rule.index');

    expect(ModerationRule::query()->count())->toBe(8);
});
