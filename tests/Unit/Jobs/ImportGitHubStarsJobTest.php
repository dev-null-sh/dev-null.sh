<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Jobs\ImportGitHubStarsJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('imports the github stars of the user', function (): void {
    $user = User::factory()->create();

    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('login')->with($user->github_id)->andReturn($user->handle);
    $github->shouldReceive('starredOwnerIds')->with($user->handle, 3)->andReturn(['1']);

    dispatch_sync(new ImportGitHubStarsJob($user));

    expect(DB::table('starred_github_owners')->where('user_id', $user->id)->pluck('github_id')->all())->toBe(['1']);
});
