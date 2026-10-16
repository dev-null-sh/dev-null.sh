<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;

function commitWithPhotos(int $count = 2): Commit
{
    $commit = Commit::factory()->create(['body' => 'Offsite photo dump']);

    CommitImage::factory()
        ->for($commit)
        ->count($count)
        ->sequence(fn (Sequence $sequence): array => ['position' => $sequence->index])
        ->create();

    return $commit;
}

it('shows the commit page with the photo open', function (): void {
    $commit = commitWithPhotos();
    Commit::factory()->for($commit, 'parent')->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit-photo.show', [$commit->user, $commit, 2]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('commit/show')
            ->where('commit.id', $commit->id)
            ->where('series', [])
            ->has('replies.data', 1)
            ->where('photo.index', 1)
            ->where('photo.commit.id', $commit->id)
            ->where('photo.commit.body', 'Offsite photo dump')
            ->has('photo.commit.images', 2)
            ->where('photo.commit.replies_count', 1));
});

it('opens series photos with the visible parts in order and replies folded', function (): void {
    $parts = commitSeries(size: 5);
    $parts[1]->delete();
    $parts[3]->update(['status' => CommitStatus::Pending]);
    $parts[4]->update(['status' => CommitStatus::Rejected]);
    CommitImage::factory()->for($parts[0])->create();
    Commit::factory()->for($parts[0], 'parent')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('commit-photo.show', [$parts[0]->user, $parts[0], 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('ancestors', [])
            ->where('replies', null)
            ->has('series', 2)
            ->where('series.0.id', $parts[1]->id)
            ->whereNot('series.0.deleted_at', null)
            ->missing('series.0.body')
            ->where('series.1.id', $parts[2]->id)
            ->where('photo.commit.replies_count', 1));
});

it('opens a middle series photo between the other parts', function (): void {
    $parts = commitSeries(size: 4);
    CommitImage::factory()->for($parts[2])->create();

    $this->actingAs(User::factory()->create())
        ->get(route('commit-photo.show', [$parts[2]->user, $parts[2], 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('ancestors', 2)
            ->where('ancestors.0.id', $parts[0]->id)
            ->where('ancestors.1.id', $parts[1]->id)
            ->where('replies', null)
            ->has('series', 1)
            ->where('series.0.id', $parts[3]->id)
            ->where('photo.commit.id', $parts[2]->id)
            ->where('photo.commit.replies_count', 0));
});

it('does not show a photo the commit does not have', function (int $photo): void {
    $commit = commitWithPhotos();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit-photo.show', [$commit->user, $commit, $photo]));

    $response->assertNotFound();
})->with([0, 3]);

it('does not show a photo of a commit that is not visible', function (): void {
    $commit = commitWithPhotos();
    $commit->update(['status' => 'pending']);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit-photo.show', [$commit->user, $commit, 1]));

    $response->assertNotFound();
});

it('does not show a photo of a deleted commit', function (): void {
    $commit = commitWithPhotos();
    $commit->delete();

    $response = $this->actingAs($commit->user)
        ->get(route('commit-photo.show', [$commit->user, $commit, 1]));

    $response->assertNotFound();
});

it('does not show a photo of a commit under another username', function (): void {
    $commit = commitWithPhotos();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit-photo.show', [User::factory()->create(), $commit, 1]));

    $response->assertNotFound();
});

it('sends guests to the commit', function (): void {
    $commit = commitWithPhotos();

    $response = $this->get(route('commit-photo.show', [$commit->user, $commit, 1]));

    $response->assertRedirectToRoute('commit.show', [$commit->user, $commit]);
});

it('does not show photos across a block in either direction', function (bool $viewerBlocks): void {
    $viewer = User::factory()->create();
    $commit = commitWithPhotos();

    $viewerBlocks ? $viewer->blocking()->attach($commit->user) : $commit->user->blocking()->attach($viewer);

    $this->actingAs($viewer)
        ->get(route('commit-photo.show', [$commit->user, $commit, 1]))
        ->assertNotFound();
})->with([true, false]);

it('loads a photo commit quote without exposing moderation hints', function (): void {
    $quoted = Commit::factory()->create(['body' => 'Quoted photo context']);
    $commit = commitWithPhotos();
    $commit->update(['quoted_commit_id' => $quoted->id, 'ai_decision' => CommitStatus::Rejected, 'ai_categories' => ['violence']]);

    $this->actingAs(User::factory()->create())
        ->get(route('commit-photo.show', [$commit->user, $commit, 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.quoted_commit.body', 'Quoted photo context')
            ->where('photo.commit.quoted_commit.id', $quoted->id)
            ->missing('commit.ai_decision')
            ->missing('photo.commit.ai_categories'));
});

it('hides quoted photo context across a block in either direction', function (bool $viewerBlocks): void {
    $viewer = User::factory()->create();
    $quoted = Commit::factory()->create(['body' => 'Blocked quote context']);
    $commit = commitWithPhotos();
    $commit->update(['quoted_commit_id' => $quoted->id]);

    $viewerBlocks ? $viewer->blocking()->attach($quoted->user) : $quoted->user->blocking()->attach($viewer);

    $this->actingAs($viewer)
        ->get(route('commit-photo.show', [$commit->user, $commit, 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.quoted_commit', null)
            ->where('photo.commit.quoted_commit', null))
        ->assertDontSee('Blocked quote context');
})->with([true, false]);
