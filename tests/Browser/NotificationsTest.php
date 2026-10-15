<?php

declare(strict_types=1);

use App\Actions\StarCommit;
use App\Models\Commit;
use App\Models\User;

it('groups stars per commit and opens the starred commit', function (): void {
    $commit = Commit::factory()->create(['body' => 'Shipped the new deploy pipeline today.']);
    $stargazers = User::factory()->count(3)->sequence(
        ['name' => 'Ada Lovelace'],
        ['name' => 'Grace Hopper'],
        ['name' => 'Linus Torvalds'],
    )->create();

    foreach ($stargazers as $index => $stargazer) {
        $this->travelTo(now()->subMinutes(3 - $index), fn () => resolve(StarCommit::class)->handle($stargazer, $commit));
    }

    $this->actingAs($commit->user);

    visit(route('notification.index'))
        ->assertSee('Linus Torvalds and 2 others starred your commit')
        ->assertSee($commit->body)
        ->click('a[href="'.route('commit.show', [$commit->user, $commit], absolute: false).'"]')
        ->assertPathIs('/@'.$commit->user->username.'/commits/'.$commit->id)
        ->assertNoJavaScriptErrors();
});
