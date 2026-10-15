<?php

declare(strict_types=1);

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
            ->has('replies.data', 1)
            ->where('photo.index', 1)
            ->where('photo.commit.id', $commit->id)
            ->where('photo.commit.body', 'Offsite photo dump')
            ->has('photo.commit.images', 2)
            ->where('photo.commit.replies_count', 1));
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
