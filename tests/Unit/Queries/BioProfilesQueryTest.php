<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Queries\BioProfilesQuery;
use Illuminate\Support\Facades\DB;

it('finds the users and organizations on dev/null the bio and company mention', function (): void {
    Organization::factory()->create(['handle' => 'Laravel']);
    User::factory()->create(['handle' => 'taylorotwell']);
    $user = User::factory()->create(['bio' => 'Thanks @TaylorOtwell, not `@laravel` in code, and @nobody', 'company' => '@laravel']);

    expect(resolve(BioProfilesQuery::class)->get($user))->toEqual(['laravel' => 'Laravel', 'taylorotwell' => 'taylorotwell']);
});

it('asks nothing for a bio that mentions no one', function (): void {
    $user = User::factory()->create(['bio' => 'Building things', 'company' => null]);

    DB::enableQueryLog();

    expect(resolve(BioProfilesQuery::class)->get($user))->toBe([]);
    expect(DB::getQueryLog())->toBe([]);
});

it('finds the profiles each user mentions for a whole page in one query', function (): void {
    Organization::factory()->create(['handle' => 'Laravel']);
    User::factory()->create(['handle' => 'taylorotwell']);
    $first = User::factory()->create(['bio' => 'Thanks @TaylorOtwell', 'company' => null]);
    $second = User::factory()->create(['bio' => 'Thanks @taylorotwell', 'company' => '@laravel']);
    $quiet = User::factory()->create(['bio' => 'Nothing to see', 'company' => null]);

    DB::enableQueryLog();

    expect(resolve(BioProfilesQuery::class)->getMany([$first, $second, $quiet]))->toEqual([
        $first->id => ['taylorotwell' => 'taylorotwell'],
        $second->id => ['taylorotwell' => 'taylorotwell', 'laravel' => 'Laravel'],
        $quiet->id => [],
    ])->and(DB::getQueryLog())->toHaveCount(1);
});

it('asks nothing when no user on the page mentions anyone', function (): void {
    $users = User::factory()->count(2)->create(['bio' => 'Building things', 'company' => null]);

    DB::enableQueryLog();

    expect(resolve(BioProfilesQuery::class)->getMany($users))->toBe([$users[0]->id => [], $users[1]->id => []])
        ->and(DB::getQueryLog())->toBe([]);
});
