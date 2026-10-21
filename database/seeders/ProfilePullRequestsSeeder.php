<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

final class ProfilePullRequestsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $user = User::query()->firstOrCreate(
            ['github_id' => 'local'],
            ['username' => 'local-dev', 'name' => 'Local Developer'],
        );

        Cache::put('github-pull-requests:'.$user->github_id, [
            'count' => 42,
            'url' => 'https://github.com/'.config()->string('services.github.repository').'/pulls',
        ], now()->addDay());
    }
}
