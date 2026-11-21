<?php

declare(strict_types=1);

use App\Actions\CreatePendingFollow;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('drops a wait for a developer who signed up or whose profile went in the meantime', function (Closure $profile): void {
    resolve(CreatePendingFollow::class)->handle(User::factory()->create(), $profile());

    $this->assertDatabaseEmpty('pending_follows');
})->with([
    'signed up' => fn (): Profile => User::factory()->create()->profile,
    'gone' => function (): Profile {
        $profile = Profile::factory()->create();
        $profile->delete();

        return $profile;
    },
]);

it('waits for a developer once, however many times asked', function (): void {
    $user = User::factory()->create();
    $profile = Profile::factory()->create();

    resolve(CreatePendingFollow::class)->handle($user, $profile);
    resolve(CreatePendingFollow::class)->handle($user, $profile);

    expect(DB::table('pending_follows')->count())->toBe(1);
});
