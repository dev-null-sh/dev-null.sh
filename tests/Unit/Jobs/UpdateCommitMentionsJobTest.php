<?php

declare(strict_types=1);

use App\Jobs\UpdateCommitMentionsJob;
use App\Models\Commit;
use App\Models\PendingMention;
use App\Models\User;

it('turns the mentions of the username the user signed in with into mentions of them, even after a rename', function (): void {
    $commit = Commit::factory()->create(['body' => 'Thanks @octocat!', 'mentions' => []]);
    PendingMention::factory()->for($commit)->create(['username' => 'octocat']);

    $user = User::factory()->create(['username' => 'renamed']);

    dispatch_sync(new UpdateCommitMentionsJob($user, 'OctoCat'));

    expect($commit->refresh()->mentions)->toBe(['octocat' => $user->id])
        ->and(PendingMention::query()->count())->toBe(0);
});
