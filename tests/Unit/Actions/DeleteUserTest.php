<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Models\Commit;
use App\Models\User;

it('may delete a user', function (): void {
    $user = User::factory()->create();

    $action = resolve(DeleteUser::class);

    $action->handle($user);

    expect($user->exists)->toBeFalse();
});

it('erases the commits of a deleted user along with the replies under them', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->create()->delete();
    Commit::factory()->for($commit, 'parent')->create();
    $unrelated = Commit::factory()->create();

    resolve(DeleteUser::class)->handle($user);

    expect(Commit::withTrashed()->pluck('id')->all())->toBe([$unrelated->id]);
});
