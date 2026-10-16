<?php

declare(strict_types=1);

use App\Actions\CreateCommitSeries;
use App\Enums\CommitStatus;
use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('shows only the first part of a series in the log with its position', function (Log $log): void {
    $user = User::factory()->create();
    $parts = commitSeries($user);

    $response = $this->actingAs($user)
        ->get(route('log.show', $log));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $parts[0]->id)
            ->where('commits.data.0.series_id', $parts[0]->series_id)
            ->where('commits.data.0.series_position', 1)
            ->where('commits.data.0.series_size', 3)
            ->where('commits.data.0.replies_count', 0));
})->with(Log::cases());

it('counts replies to a part without the rest of the series', function (): void {
    $user = User::factory()->create();
    $parts = commitSeries($user);
    Commit::factory()->for($parts[0], 'parent')->create();

    $response = $this->actingAs($user)
        ->get(route('log.show', Log::Following));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('commits.data.0.replies_count', 1));
});

it('lists replies to a part without the next part of the series', function (): void {
    $user = User::factory()->create();
    $parts = commitSeries($user);
    $reply = Commit::factory()->for($parts[0], 'parent')->create();

    $response = $this->actingAs($user)
        ->getJson(route('commit-reply.index', $parts[0]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $reply->id);
});

it('does not list later parts of a series as replies on the profile', function (): void {
    $user = User::factory()->create();
    $parts = commitSeries($user);
    $reply = Commit::factory()->for($user)->for(Commit::factory(), 'parent')->create();

    $response = $this->actingAs($user)
        ->get(route('user.show', ['user' => $user, 'tab' => 'replies']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $reply->id));

    $this->actingAs($user)
        ->get(route('user.show', $user))
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $parts[0]->id));
});

it('shows the first part of a series with the rest of the series after it', function (): void {
    $viewer = User::factory()->create();
    $parts = commitSeries();

    $response = $this->actingAs($viewer)
        ->get(route('commit.show', [$parts[0]->user, $parts[0]]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('commit/show')
            ->where('commit.id', $parts[0]->id)
            ->where('commit.series_position', 1)
            ->has('ancestors', 0)
            ->has('series', 2)
            ->where('series.0.id', $parts[1]->id)
            ->where('series.0.series_position', 2)
            ->where('series.0.replies_count', 0)
            ->where('series.0.starred', false)
            ->where('series.0.is_saved', false)
            ->has('series.0.user')
            ->where('series.1.id', $parts[2]->id)
            ->where('replies', null));
});

it('shows a middle part of a series between the parts around it', function (): void {
    $viewer = User::factory()->create();
    $parts = commitSeries(size: 4);

    $response = $this->actingAs($viewer)
        ->get(route('commit.show', [$parts[2]->user, $parts[2]]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.id', $parts[2]->id)
            ->where('ancestors.0.id', $parts[0]->id)
            ->where('ancestors.1.id', $parts[1]->id)
            ->has('ancestors', 2)
            ->where('series.0.id', $parts[3]->id)
            ->has('series', 1));
});

it('hides muted series from logs but keeps every part reachable directly', function (Log $log): void {
    $viewer = User::factory()->create();
    $parts = commitSeries();
    $viewer->following()->attach($parts[0]->user);
    $viewer->muting()->attach($parts[0]->user);

    $this->actingAs($viewer)
        ->get(route('log.show', $log))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('commits.data', 0));

    $this->get(route('commit.show', [$parts[1]->user, $parts[1]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.id', $parts[1]->id)
            ->has('ancestors', 1)
            ->where('ancestors.0.id', $parts[0]->id)
            ->has('series', 1)
            ->where('series.0.id', $parts[2]->id));
})->with(Log::cases());

it('hides every part of a series across a block in either direction', function (bool $viewerBlocks): void {
    $viewer = User::factory()->create();
    $parts = commitSeries();

    $viewerBlocks ? $viewer->blocking()->attach($parts[0]->user) : $parts[0]->user->blocking()->attach($viewer);

    foreach ($parts as $part) {
        $this->actingAs($viewer)
            ->get(route('commit.show', [$part->user, $part]))
            ->assertNotFound();
    }
})->with([true, false]);

it('excludes muted and blocked replies along with series continuations from reply counts', function (): void {
    $viewer = User::factory()->create();
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $parts = commitSeries();
    $viewer->muting()->attach($muted);
    $blocked->blocking()->attach($viewer);

    Commit::factory()->for($parts[0], 'parent')->for($muted)->create();
    Commit::factory()->for($parts[0], 'parent')->for($blocked)->create();
    $reply = Commit::factory()->for($parts[0], 'parent')->create();

    $this->actingAs($viewer)
        ->get(route('commit.show', [$parts[0]->user, $parts[0]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('commit.replies_count', 1));

    $this->getJson(route('commit-reply.index', $parts[0]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $reply->id);
});

it('checks moderation visibility separately for each surrounding part', function (): void {
    $viewer = User::factory()->create();
    $parts = commitSeries(size: 4);
    $parts[0]->update(['status' => CommitStatus::Pending]);
    $parts[2]->update(['status' => CommitStatus::Rejected]);

    $this->actingAs($viewer)
        ->get(route('commit.show', [$parts[1]->user, $parts[1]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('ancestors', 0)
            ->has('series', 1)
            ->where('series.0.id', $parts[3]->id));

    $this->actingAs($parts[0]->user)
        ->get(route('commit.show', [$parts[1]->user, $parts[1]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('ancestors', 1)
            ->where('ancestors.0.id', $parts[0]->id)
            ->has('series', 2)
            ->where('series.0.id', $parts[2]->id));
});

it('keeps deleted parts of a series as tombstones with their position', function (): void {
    $viewer = User::factory()->create();
    $parts = commitSeries();
    $parts[1]->delete();

    $response = $this->actingAs($viewer)
        ->get(route('commit.show', [$parts[0]->user, $parts[0]]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('series.0.id', $parts[1]->id)
            ->where('series.0.series_position', 2)
            ->where('series.0.series_size', 3)
            ->whereNot('series.0.deleted_at', null)
            ->missing('series.0.body')
            ->missing('series.0.user')
            ->where('series.1.id', $parts[2]->id));
});

it('does not show a pending series to other users', function (): void {
    $parts = commitSeries(status: CommitStatus::Pending);

    $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$parts[0]->user, $parts[0]]))
        ->assertNotFound();

    $this->actingAs($parts[0]->user)
        ->get(route('commit.show', [$parts[0]->user, $parts[0]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('series', 2));
});

it('shows a commit outside a series without a series', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$commit->user, $commit]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('series', [])
            ->has('replies.data', 0));
});

it('loads a series with the same number of queries however long it is', function (): void {
    $viewer = User::factory()->create();

    $queriesFor = function (Commit $part) use ($viewer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($viewer)
            ->get(route('commit.show', [$part->user, $part]))
            ->assertOk();

        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $short = commitSeries(size: 3);
    $long = commitSeries(size: 10);

    expect($queriesFor($long[5]))->toBe($queriesFor($short[1]));
});

it('splits the code of every part of a series like that of a single commit', function (): void {
    $parts = resolve(CreateCommitSeries::class)->handle(User::factory()->create(), ['Run `make` first', "Then this:\n```\nphp artisan migrate\n```"]);
    Commit::query()->whereKey($parts->modelKeys())->update(['status' => CommitStatus::Approved]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('commit.show', [$parts[0]->user, $parts[0]]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.body_segments.1', ['type' => 'code', 'code' => 'make'])
            ->where('series.0.body_segments.1.type', 'block'));
});
