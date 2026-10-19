<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Models\User;

it('keeps the mentions of developers who are not on devnull yet', function (): void {
    $author = User::factory()->create();
    User::factory()->create(['username' => 'taylor']);

    $commit = resolve(CreateCommit::class)->handle($author, "Thanks @taylor, @OndrejMirtes and @ondrejmirtes!\n`@codeuser`");

    expect($commit->pendingMentions()->pluck('username')->all())->toBe(['ondrejmirtes']);
});

it('keeps nothing when everyone mentioned is on devnull', function (): void {
    $author = User::factory()->create();
    User::factory()->create(['username' => 'taylor']);

    $commit = resolve(CreateCommit::class)->handle($author, 'Thanks @taylor!');

    expect($commit->pendingMentions()->count())->toBe(0);
});
