<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\PendingMention;
use App\Models\User;

it('keeps the mentions of developers who are not on devnull from commits made before, but not of those who left', function (): void {
    User::factory()->create(['username' => 'Taylor']);

    $commit = Commit::factory()->create(['body' => "Thanks @taylor, @OndrejMirtes and @nuno!\n`@codeuser`"]);
    $other = Commit::factory()->create(['body' => 'cc @ondrejmirtes']);
    $left = Commit::factory()->create(['body' => 'Thanks @alice!', 'mentions' => ['alice' => '019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b']]);
    Commit::factory()->create(['body' => 'No mentions here.']);
    PendingMention::factory()->for($other)->create(['username' => 'ondrejmirtes']);

    $migration = require database_path('migrations/2026_10_19_170001_backfill_pending_mentions.php');
    $migration->up();

    expect($commit->pendingMentions()->orderBy('username')->pluck('username')->all())->toBe(['nuno', 'ondrejmirtes'])
        ->and($other->pendingMentions()->pluck('username')->all())->toBe(['ondrejmirtes'])
        ->and($left->pendingMentions()->count())->toBe(0);
});
