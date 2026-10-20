<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('lists the signed users starred commits', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    $user->starredCommits()->attach($commit);

    signedApiRequest(apiKeyPairFor($user), 'GET', route('api-starred.index'))
        ->assertOk()->assertJsonPath('data.0.id', $commit->id);
});
