<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ProfileType;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateLocalUser
{
    /**
     * Locally, anyone can sign in as any handle, without GitHub: the first time creates the user and their profile,
     * under a GitHub ID no GitHub account can have.
     */
    public function handle(string $handle): User
    {
        return User::query()->whereRelation('profile', 'handle', $handle)->first() ?? DB::transaction(function () use ($handle): User {
            $profile = Profile::query()->create([
                'type' => ProfileType::User,
                'github_id' => "local:{$handle}",
                'handle' => $handle,
                'name' => $handle,
            ]);

            return User::query()->create(['github_id' => $profile->github_id]);
        });
    }
}
