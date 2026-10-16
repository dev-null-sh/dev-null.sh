<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitClaim;
use App\Models\CommitDecision;
use App\Models\LinkPreview;
use App\Models\RejectionReason;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('renders pending commits oldest first', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);

    Commit::factory()->pending()->create(['body' => 'Newer']);
    Commit::factory()->pending()->create(['body' => 'Older', 'created_at' => now()->subMinute()]);
    Commit::factory()->create();
    Commit::factory()->rejected()->create();

    $response = $this->actingAs($admin)
        ->get(route('moderation.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('moderation/show')
            ->has('commits.data', 2)
            ->where('commits.data.0.body', 'Older')
            ->where('commits.data.0.stars_count', 0)
            ->where('commits.data.0.starred', false)
            ->where('commits.data.1.body', 'Newer')
            ->has('commits.data.0.user'));
});

it('drops deleted pending commits from the queue', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);

    Commit::factory()->pending()->create(['body' => 'Kept']);
    Commit::factory()->pending()->create()->delete();

    $response = $this->actingAs($admin)
        ->get(route('moderation.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.body', 'Kept'));
});

it('may not be viewed by non admins', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('moderation.show'));

    $response->assertForbidden();
});

it('requires authentication', function (): void {
    $response = $this->get(route('moderation.show'));

    $response->assertRedirectToRoute('home');
});

it('shows who a pending reply is replying to', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $parent = Commit::factory()->for(User::factory()->create(['username' => 'octocat']))->create();
    Commit::factory()->pending()->for($parent, 'parent')->create();

    $response = $this->actingAs($admin)
        ->get(route('moderation.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.parent.user.username', 'octocat'));
});

it('includes the link previews of pending commits', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);

    $linkPreview = LinkPreview::factory()->fetched()->create(['title' => 'Pending link']);

    Commit::factory()->pending()->for($linkPreview)->create();

    $response = $this->actingAs($admin)
        ->get(route('moderation.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.link_preview.title', 'Pending link'));
});

it('may approve many commits', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commits = Commit::factory()->pending()->count(2)->create();
    $other = Commit::factory()->pending()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->put(route('moderation.update'), [
            'commits' => $commits->modelKeys(),
        ]);

    $response->assertRedirectToRoute('moderation.show');

    foreach ($commits as $commit) {
        expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
    }

    expect($other->refresh()->status)->toBe(CommitStatus::Pending);
});

it('only approves commits that are still pending', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $rejected = Commit::factory()->rejected()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->put(route('moderation.update'), [
            'commits' => [$rejected->id],
        ]);

    $response->assertRedirectToRoute('moderation.show');

    expect($rejected->refresh()->status)->toBe(CommitStatus::Rejected);
});

it('requires valid commits', function (mixed $commits): void {
    $admin = User::factory()->create(['username' => 'admin']);

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->put(route('moderation.update'), [
            'commits' => $commits,
        ]);

    $response->assertRedirectToRoute('moderation.show')
        ->assertSessionHasErrors('commits');
})->with([
    'missing' => [null],
    'empty' => [[]],
    'not an array' => ['not-an-array'],
    'too many' => [array_fill(0, 101, '0199a3b2-0000-7000-8000-000000000000')],
]);

it('may not approve many commits as a non admin', function (): void {
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($commit->user)
        ->put(route('moderation.update'), [
            'commits' => [$commit->id],
        ]);

    $response->assertForbidden();

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
});

it('renders the rejection reasons in alphabetical order', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    RejectionReason::factory()->create(['title' => 'Spam', 'body' => 'This looks like spam.']);
    RejectionReason::factory()->create(['title' => 'Off-topic']);

    $response = $this->actingAs($admin)
        ->get(route('moderation.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rejectionReasons', 2)
            ->where('rejectionReasons.0.title', 'Off-topic')
            ->where('rejectionReasons.1.title', 'Spam')
            ->where('rejectionReasons.1.body', 'This looks like spam.'));
});

it('renders who is reviewing which commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();
    CommitClaim::factory()->for($commit)->for(User::factory()->create(['username' => 'other']))->create();
    CommitClaim::factory()->expired()->create();

    $response = $this->actingAs($admin)
        ->get(route('moderation.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('claims', 1)
            ->where("claims.{$commit->id}.user.username", 'other')
            ->has("claims.{$commit->id}.expires_at"));
});

it('records who approved many commits', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commits = Commit::factory()->pending()->count(2)->create();

    $this->actingAs($admin)
        ->put(route('moderation.update'), [
            'commits' => $commits->modelKeys(),
        ]);

    expect(CommitDecision::query()->where('user_id', $admin->id)->pluck('commit_id')->all())
        ->toEqualCanonicalizing($commits->modelKeys());
});

it('may not approve many commits when another admin is reviewing one of them', function (): void {
    config(['app.admins' => ['admin', 'other']]);

    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();
    $claimed = Commit::factory()->pending()->create();
    CommitClaim::factory()->for($claimed)->for(User::factory()->create(['username' => 'other']))->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->put(route('moderation.update'), [
            'commits' => [$commit->id, $claimed->id],
        ]);

    $response->assertRedirectToRoute('moderation.show')
        ->assertSessionHasErrors(['commits.1' => '@other is reviewing this commit.']);

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
});
