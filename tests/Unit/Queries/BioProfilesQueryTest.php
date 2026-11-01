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
