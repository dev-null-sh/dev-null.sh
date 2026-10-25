<?php

declare(strict_types=1);

use App\Models\Issue;
use App\Models\User;

test('to array', function (): void {
    $issue = Issue::factory()->create()->refresh();

    expect(array_keys($issue->toArray()))
        ->toBe([
            'id',
            'user_id',
            'repository',
            'number',
            'title',
            'url',
            'created_at',
            'updated_at',
        ]);
});

it('belongs to the user who opened it', function (): void {
    $user = User::factory()->create();
    $issue = Issue::factory()->for($user)->create();

    expect($issue->user->is($user))->toBeTrue();
});
