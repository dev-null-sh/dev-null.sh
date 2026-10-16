<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\CommitClaim;
use App\Models\CommitDecision;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);

    $this->admin = User::factory()->create(['username' => 'admin']);
});

it('lists a pending series once in the queue with the rest of its parts in order', function (): void {
    $parts = commitSeries(status: CommitStatus::Pending);
    $single = Commit::factory()->pending()->create(['created_at' => now()->addSecond()]);

    $response = $this->actingAs($this->admin)
        ->get(route('moderation.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 2)
            ->where('commits.data.0.id', $parts[0]->id)
            ->has('commits.data.0.series_parts', 3)
            ->where('commits.data.0.series_parts.1.id', $parts[1]->id)
            ->where('commits.data.0.series_parts.2.id', $parts[2]->id)
            ->has('commits.data.0.series_parts.2.user')
            ->has('commits.data.0.series_parts.2.body_segments')
            ->where('commits.data.1.id', $single->id)
            ->has('commits.data.1.series_parts', 0));
});

it('shows the quoted commit of each pending series part to moderators', function (): void {
    $parts = commitSeries(status: CommitStatus::Pending);
    $quoted = Commit::factory()->rejected()->create();
    $parts[1]->update(['quoted_commit_id' => $quoted->id]);

    $this->actingAs($this->admin)
        ->get(route('moderation.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.series_parts.1.quoted_commit.body', $quoted->body)
            ->where('commits.data.0.series_parts.1.quoted_commit.user.id', $quoted->user_id));
});

it('notifies quoted authors about each series part only after approval', function (): void {
    $parts = commitSeries(status: CommitStatus::Pending);
    $quoted = Commit::factory()->create();
    $parts[0]->update(['quoted_commit_id' => $quoted->id]);
    $parts[1]->update(['quoted_commit_id' => $quoted->id]);

    expect($quoted->user->notifications()->count())->toBe(0);

    $this->actingAs($this->admin)
        ->post(route('commit-approval.store', $parts[1]))
        ->assertRedirect();

    expect($quoted->user->notifications()->where('type', NotificationType::Quoted)->pluck('commit_id')->sort()->values()->all())
        ->toBe(collect([$parts[0]->id, $parts[1]->id])->sort()->values()->all());
});

it('lists a series under its next part once its first part is deleted', function (): void {
    $parts = commitSeries(status: CommitStatus::Pending);
    $parts[0]->delete();

    $response = $this->actingAs($this->admin)
        ->get(route('moderation.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $parts[1]->id)
            ->has('commits.data.0.series_parts', 2));
});

it('loads the queue with the same number of queries however many series wait', function (): void {
    $queries = function (): int {
        DB::flushQueryLog();
        Cache::flush();
        DB::enableQueryLog();

        $this->actingAs($this->admin)->get(route('moderation.show'))->assertOk();

        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    commitSeries(status: CommitStatus::Pending);
    $once = $queries();

    commitSeries(status: CommitStatus::Pending);
    commitSeries(size: 5, status: CommitStatus::Pending);

    expect($queries())->toBe($once);
});

it('approves every part of a series at once without notifying the author', function (): void {
    $parts = commitSeries(status: CommitStatus::Pending);

    $this->actingAs($this->admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $parts[1]))
        ->assertRedirectToRoute('moderation.show');

    expect(Commit::query()->whereKey($parts->modelKeys())->pluck('status')->unique()->all())->toBe([CommitStatus::Approved])
        ->and($parts[0]->user->notifications()->count())->toBe(0);
});

it('rejects every part of a series at once so no part is left without the one before it', function (): void {
    $parts = commitSeries(status: CommitStatus::Pending);

    $this->actingAs($this->admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-rejection.store', $parts[2]), ['reason' => 'Spam'])
        ->assertRedirectToRoute('moderation.show');

    expect(Commit::query()->whereKey($parts->modelKeys())->pluck('status')->unique()->all())->toBe([CommitStatus::Rejected])
        ->and($parts[0]->user->notifications()->sole())
        ->type->toBe(NotificationType::Rejected)
        ->commit_id->toBe($parts[0]->id)
        ->reason->toBe('Spam');
});

it('notifies about the first remaining part when the first part of a series was deleted', function (): void {
    $parts = commitSeries(status: CommitStatus::Pending);
    $parts[0]->delete();

    $this->actingAs($this->admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-rejection.store', $parts[1]), ['reason' => 'Spam']);

    expect($parts[0]->user->notifications()->sole()->commit_id)->toBe($parts[1]->id);
});

it('approves the whole series when approving the selected commits', function (): void {
    $parts = commitSeries(status: CommitStatus::Pending);
    $other = Commit::factory()->pending()->create();

    $this->actingAs($this->admin)
        ->fromRoute('moderation.show')
        ->put(route('moderation.update'), ['commits' => [$parts[0]->id, $other->id]])
        ->assertRedirectToRoute('moderation.show');

    expect(Commit::query()->pending()->count())->toBe(0)
        ->and(Notification::query()->count())->toBe(0);
});

it('respects claims on every series part before changing the series', function (string $route, string $method, int $target, int $claimed): void {
    config(['app.admins' => ['admin', 'other']]);
    $other = User::factory()->create(['username' => 'other']);
    $parts = commitSeries(status: CommitStatus::Pending);
    CommitClaim::factory()->for($parts[$claimed])->for($other)->create();
    $isBulk = $route === 'moderation.update';

    $this->actingAs($this->admin)
        ->fromRoute('moderation.show')
        ->{$method}(route($route, $isBulk ? [] : $parts[$target]), ['commits' => [$parts[$target]->id]])
        ->assertRedirectToRoute('moderation.show')
        ->assertSessionHasErrors($isBulk ? 'commits.0' : 'commit');

    expect($parts->fresh()->pluck('status')->unique()->all())->toBe([CommitStatus::Pending])
        ->and(CommitDecision::query()->count())->toBe(0)
        ->and(CommitClaim::query()->sole()->user_id)->toBe($other->id);
})->with([
    ['commit-approval.store', 'post', 2, 0],
    ['commit-rejection.store', 'post', 0, 2],
    ['moderation.update', 'put', 0, 2],
    ['commit-claim.update', 'put', 2, 0],
]);

it('takes over the series claim under its first pending part', function (bool $deleteFirst): void {
    config(['app.admins' => ['admin', 'other']]);
    $other = User::factory()->create(['username' => 'other']);
    $parts = commitSeries(status: CommitStatus::Pending);
    CommitClaim::factory()->for($parts[2])->for($other)->create();

    if ($deleteFirst) {
        $parts[0]->delete();
    }

    $this->actingAs($this->admin)
        ->fromRoute('moderation.show')
        ->put(route('commit-claim.update', $parts[2]), ['take_over' => true])
        ->assertRedirectToRoute('moderation.show')
        ->assertSessionHasNoErrors();

    $claim = CommitClaim::query()->sole();
    expect($claim->commit_id)->toBe($parts[$deleteFirst ? 1 : 0]->id)
        ->and($claim->user_id)->toBe($this->admin->id);
})->with([false, true]);

it('records a decision for every moderated part and releases the series claim', function (string $route, CommitStatus $status): void {
    $parts = commitSeries(status: CommitStatus::Pending);
    CommitClaim::factory()->for($parts[0])->for($this->admin)->create();

    $this->actingAs($this->admin)
        ->fromRoute('moderation.show')
        ->post(route($route, $parts[2]), ['reason' => 'Spam template'])
        ->assertRedirectToRoute('moderation.show')
        ->assertSessionHasNoErrors();

    foreach ($parts as $part) {
        $decision = $part->decisions()->sole();
        expect($decision->user_id)->toBe($this->admin->id)
            ->and($decision->status)->toBe($status)
            ->and($decision->reason)->toBe($status === CommitStatus::Rejected ? 'Spam template' : null);
    }

    expect(CommitClaim::query()->count())->toBe(0)
        ->and($parts[0]->user->notifications()->count())->toBe($status === CommitStatus::Rejected ? 1 : 0);

    $this->get(route('commit-decision.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('decisions.data', 3)
            ->where('decisions.data.0.user.id', $this->admin->id)
            ->where('decisions.data.0.status', $status->value));

    if ($status === CommitStatus::Approved) {
        $this->get(route('commit.show', [$parts[0]->user, $parts[1]]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('decisions.0.user.id', $this->admin->id)
                ->where('decisions.0.status', $status->value));
    }

    $this->actingAs($parts[0]->user)
        ->get(route('commit.show', [$parts[0]->user, $parts[1]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('decisions', 0));
})->with([
    ['commit-approval.store', CommitStatus::Approved],
    ['commit-rejection.store', CommitStatus::Rejected],
]);
