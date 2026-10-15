<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Models\Commit;

it('may delete a commit', function (): void {
    $commit = Commit::factory()->create();

    $action = resolve(DeleteCommit::class);

    $action->handle($commit);

    $this->assertSoftDeleted($commit);
});

it('keeps the replies of a deleted commit', function (): void {
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->for($commit, 'parent')->create();

    resolve(DeleteCommit::class)->handle($commit);

    $this->assertNotSoftDeleted($reply);
});
