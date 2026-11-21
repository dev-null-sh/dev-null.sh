<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Profile;
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

it('says what each profile the bio and company mention opens as, in one query', function (): void {
    Organization::factory()->create(['handle' => 'Laravel']);
    User::factory()->create(['handle' => 'taylorotwell']);
    Profile::factory()->create(['handle' => 'Unclaimed']);
    $user = User::factory()->create(['bio' => 'Thanks @TaylorOtwell, @unclaimed, not `@laravel` in code, and @nobody', 'company' => '@laravel']);

    DB::enableQueryLog();

    expect(resolve(BioProfilesQuery::class)->types($user))->toEqual(['laravel' => 'organization', 'taylorotwell' => 'user', 'unclaimed' => 'unclaimed']);
    expect(DB::getQueryLog())->toHaveCount(1);
});

it('asks nothing for the types of a bio that mentions no one', function (): void {
    $user = User::factory()->create(['bio' => 'Building things', 'company' => null]);

    DB::enableQueryLog();

    expect(resolve(BioProfilesQuery::class)->types($user))->toBe([]);
    expect(DB::getQueryLog())->toBe([]);
});
