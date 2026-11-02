<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Headline;
use App\Models\User;

it('requires sign in to read headline discussions', function (): void {
    $headline = Headline::factory()->create();

    $this->get(route('headline.show', $headline))->assertRedirectToRoute('home');
});

it('shows a fresh recap and semantic matches from the past week, not just linked posts', function (): void {
    $headline = Headline::factory()->create(['name' => 'Database indexing', 'summary' => 'Developers compare indexing strategies.', 'summary_created_at' => now()->subHour()]);
    $recent = Commit::factory()->create(['embedding' => embeddingVector(0), 'created_at' => now()->subWeek()]);
    Commit::factory()->create(['embedding' => embeddingVector(0), 'created_at' => now()->subWeek()->subSecond()]);

    $this->actingAs(User::factory()->create())->get(route('headline.show', $headline))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->component('headline/show')
        ->where('name', 'Database indexing')
        ->where('summary', 'Developers compare indexing strategies.')
        ->has('commits', 1)->where('commits.0.id', $recent->id))
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});

it('omits unavailable or expired recaps without hiding the discussions', function (?string $summary, ?string $created): void {
    $headline = Headline::factory()->create(['summary' => $summary, 'summary_created_at' => $created === null ? null : now()->modify($created)]);
    $commit = Commit::factory()->create(['embedding' => embeddingVector(0)]);

    $this->actingAs(User::factory()->create())->get(route('headline.show', $headline))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('summary', null)->has('commits', 1)->where('commits.0.id', $commit->id));
})->with([
    'no useful recap' => [null, null],
    'no timestamp' => ['An incomplete recap.', null],
    'exactly expired' => ['An old recap.', '-2 hours'],
]);

it('does not repeat muted or blocked authors through a shared recap', function (string $relationship): void {
    $viewer = User::factory()->create();
    $hidden = User::factory()->create();
    $viewer->{$relationship}()->attach($hidden);
    $headline = Headline::factory()->create(['summary' => 'A recap with hidden contributions.', 'summary_created_at' => now()]);
    Commit::factory()->for($hidden)->hasAttached($headline)->create(['embedding' => embeddingVector(0)]);
    $visible = Commit::factory()->create(['embedding' => embeddingVector(0)]);

    $this->actingAs($viewer)->get(route('headline.show', $headline))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('summary', null)->has('commits', 1)->where('commits.0.id', $visible->id));
})->with(['muting', 'blocking']);

it('returns not found for an unknown headline', function (): void {
    $this->actingAs(User::factory()->create())->get('/headlines/00000000-0000-4000-8000-000000000000')->assertNotFound();
});

it('rate limits headline semantic searches', function (): void {
    $headline = Headline::factory()->create();
    $this->actingAs(User::factory()->create());

    for ($attempt = 0; $attempt < 15; $attempt++) {
        $this->get(route('headline.show', $headline))->assertOk();
    }

    $this->get(route('headline.show', $headline))->assertTooManyRequests();
});
