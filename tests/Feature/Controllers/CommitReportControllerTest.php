<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\CommitReport;
use App\Models\LinkPreview;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use App\Services\EncryptedFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

beforeEach(function (): void {
    config(['app.admins' => ['moderator-demo']]);
});

it('privately reports an approved commit or reply without changing visibility', function (bool $reply): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create(['parent_id' => $reply ? Commit::factory()->create()->id : null]);

    $this->actingAs($viewer)->fromRoute('log.show', 'everyone')
        ->post(route('commit-report.store', $commit), ['reason' => 'spam', 'details' => 'Misleading package link.'])
        ->assertRedirectToRoute('log.show', 'everyone');

    $report = CommitReport::query()->sole();
    expect($report->reporter_id)->toBe($viewer->id)
        ->and($report->commit_id)->toBe($commit->id)
        ->and($report->reason)->toBe('spam')
        ->and($report->details)->toBe('Misleading package link.')
        ->and($commit->refresh()->status)->toBe(CommitStatus::Approved);

    $this->get(route('commit.show', [$commit->user, $commit]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('commit.body', $commit->body)->missing('reports')->missing('commit.reports')->missing('commit.reports_count')->missing('commit.reporter_id'));

    $this->getJson(route('api-commit.show', $commit))->assertOk()
        ->assertJsonPath('data.body', $commit->body)
        ->assertJsonMissingPath('data.reports')
        ->assertJsonMissingPath('data.reports_count')
        ->assertJsonMissingPath('data.reporter_id')
        ->assertDontSee($report->details)
        ->assertDontSee($report->id);

    $this->getJson(route('api-user-commit.index', $commit->user))->assertOk()
        ->assertDontSee($report->details)
        ->assertDontSee($report->id);
    $this->get(route('user-feed.show', $commit->user))->assertOk()
        ->assertDontSee($report->details)
        ->assertDontSee($report->id);
})->with([false, true]);

it('accepts each reason with optional trimmed details', function (string $reason): void {
    $commit = Commit::factory()->create();

    $this->actingAs(User::factory()->create())->post(route('commit-report.store', $commit), ['reason' => $reason, 'details' => '   '])->assertRedirect();

    expect(CommitReport::query()->sole()->details)->toBeNull();
})->with(array_keys(CommitReport::REASONS));

it('requires authentication', function (): void {
    $this->post(route('commit-report.store', Commit::factory()->create()), ['reason' => 'spam'])->assertRedirectToRoute('home');
    $this->get(route('reported-commit.index'))->assertRedirectToRoute('home');
    expect(CommitReport::query()->count())->toBe(0);
});

it('includes link previews in the moderator report context', function (): void {
    $preview = LinkPreview::factory()->fetched()->create(['url' => 'https://example.com/docs']);
    $commit = Commit::factory()->create(['link_preview_id' => $preview->id]);
    CommitReport::factory()->create(['commit_id' => $commit->id]);

    $this->actingAs(User::factory()->create(['handle' => 'moderator-demo']))
        ->get(route('reported-commit.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('reports.data.0.commit.link_preview.url', $preview->url));
});

it('includes images in the moderator report context without querying per report', function (): void {
    $reports = CommitReport::factory()->count(2)->create();
    $images = $reports->map(fn (CommitReport $report): CommitImage => CommitImage::factory()->for($report->commit)->create());
    $moderator = User::factory()->create(['handle' => 'moderator-demo']);

    DB::enableQueryLog();

    $this->actingAs($moderator)
        ->get(route('reported-commit.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('reports.data', fn (Collection $data): bool => $data->pluck('commit.images.0.url')->sort()->values()->all() === $images->map(fn (CommitImage $image): string => $image->url())->sort()->values()->all()));

    expect(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'commit_images')))->toHaveCount(1);
});

it('includes reported polls with viewer specific results and card state', function (bool $voted, bool $closed): void {
    $moderator = User::factory()->create(['handle' => 'moderator-demo']);
    $factory = Poll::factory()->withOptions('Tabs', 'Spaces');
    $poll = ($closed ? $factory->closed() : $factory)->create();
    PollVote::factory()->for($poll->options[0], 'option')->for($voted ? $moderator : User::factory())->create();
    $poll->commit->stars()->attach($moderator);
    CommitReport::factory()->for($poll->commit)->create();

    $this->actingAs($moderator)->get(route('reported-commit.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reports.data.0.commit.poll.options.0.label', 'Tabs')
            ->where('reports.data.0.commit.starred', true)
            ->where('reports.data.0.commit.poll.has_voted', $voted)
            ->where('reports.data.0.commit.poll.is_closed', $closed)
            ->where('reports.data.0.commit.poll.total_votes', $voted || $closed ? 1 : null)
            ->where('reports.data.0.commit.poll.options.0.votes_count', $voted || $closed ? 1 : null)
            ->missing('reports.data.0.commit.poll.votes')
            ->missing('reports.data.0.reporter_id'));
})->with([false, true])->with([false, true]);

it('includes visible quoted context in reports without exposing ai advice or deleted content', function (bool $deleted): void {
    $quoted = Commit::factory()->create(['ai_decision' => CommitStatus::Rejected, 'ai_categories' => ['private-quoted-category']]);
    $quote = Commit::factory()->for($quoted, 'quotedCommit')->create(['ai_decision' => CommitStatus::Approved, 'ai_categories' => []]);
    CommitReport::factory()->for($quote)->create();

    if ($deleted) {
        $quoted->delete();
    }

    $this->actingAs(User::factory()->create(['handle' => 'moderator-demo']))
        ->get(route('reported-commit.index'))->assertOk()
        ->assertInertia(function ($page) use ($quoted, $deleted): void {
            $page->where('reports.data.0.commit.quoted_commit.id', $quoted->id)
                ->missing('reports.data.0.reporter_id')
                ->missing('reports.data.0.commit.ai_decision')
                ->missing('reports.data.0.commit.ai_categories')
                ->missing('reports.data.0.commit.quoted_commit.ai_decision')
                ->missing('reports.data.0.commit.quoted_commit.ai_categories');

            if ($deleted) {
                $page->missing('reports.data.0.commit.quoted_commit.body')
                    ->missing('reports.data.0.commit.quoted_commit.user')
                    ->missing('reports.data.0.commit.quoted_commit.user_id');
            } else {
                $page->where('reports.data.0.commit.quoted_commit.body', $quoted->body)
                    ->where('reports.data.0.commit.quoted_commit.user.id', $quoted->user_id);
            }
        });
})->with([false, true]);

it('hides quoted context the report reviewer cannot see', function (string $state): void {
    $moderator = User::factory()->create(['handle' => 'moderator-demo']);
    $quoted = Commit::factory()->create(['status' => in_array($state, ['pending', 'rejected'], true) ? $state : CommitStatus::Approved]);
    $quote = Commit::factory()->for($quoted, 'quotedCommit')->create();
    CommitReport::factory()->for($quote)->create();

    if ($state === 'blocking') {
        $moderator->blocking()->attach($quoted->user);
    } elseif ($state === 'blocked') {
        $quoted->user->blocking()->attach($moderator);
    }

    $this->actingAs($moderator)->get(route('reported-commit.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('reports.data.0.commit.id', $quote->id)
            ->where('reports.data.0.commit.quoted_commit', null));
})->with(['pending', 'rejected', 'blocking', 'blocked']);

it('does not report hidden targets even for their authors or moderators', function (string $status, string $viewer): void {
    $commit = Commit::factory()->create(['status' => $status]);
    $user = match ($viewer) {
        'author' => $commit->user,
        'moderator' => User::factory()->create(['handle' => 'moderator-demo']),
        default => User::factory()->create(),
    };

    $this->actingAs($user)->post(route('commit-report.store', $commit), ['reason' => 'spam'])->assertNotFound();
    expect(CommitReport::query()->count())->toBe(0);
})->with(['pending', 'rejected'])->with(['other', 'author', 'moderator']);

it('does not report deleted or nonexistent targets', function (): void {
    $commit = Commit::factory()->create();
    $commit->delete();
    $this->actingAs(User::factory()->create())->post(route('commit-report.store', $commit), ['reason' => 'spam'])->assertNotFound();
    $commit->forceDelete();
    $this->post(route('commit-report.store', $commit->id), ['reason' => 'spam'])->assertNotFound();
    expect(CommitReport::query()->count())->toBe(0);
});

it('rejects self reports', function (): void {
    $commit = Commit::factory()->create();
    $this->actingAs($commit->user)->post(route('commit-report.store', $commit), ['reason' => 'spam'])->assertForbidden();
    expect(CommitReport::query()->count())->toBe(0);
});

it('does not report commits across a block in either direction', function (bool $reporterBlocks): void {
    $reporter = User::factory()->create();
    $commit = Commit::factory()->create();

    if ($reporterBlocks) {
        $reporter->blocking()->attach($commit->user);
    } else {
        $commit->user->blocking()->attach($reporter);
    }

    $this->actingAs($reporter)->post(route('commit-report.store', $commit), ['reason' => 'spam'])->assertNotFound();

    expect(CommitReport::query()->count())->toBe(0);
})->with([true, false]);

it('can report a muted author from their directly accessible commit', function (): void {
    $reporter = User::factory()->create();
    $commit = Commit::factory()->create();
    $reporter->muting()->attach($commit->user);

    $this->actingAs($reporter)->post(route('commit-report.store', $commit), ['reason' => 'spam'])->assertRedirect();

    expect(CommitReport::query()->sole()->commit_id)->toBe($commit->id);
});

it('hides blocked parent context in the reports queue in either direction', function (bool $moderatorBlocks): void {
    $moderator = User::factory()->create(['handle' => 'moderator-demo']);
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    CommitReport::factory()->for($reply)->create();
    CommitReport::factory()->for($parent)->create();

    if ($moderatorBlocks) {
        $moderator->blocking()->attach($parent->user);
    } else {
        $parent->user->blocking()->attach($moderator);
    }

    $this->actingAs($moderator)->get(route('reported-commit.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('reports.data', 1)
            ->where('reports.data.0.commit.id', $reply->id)
            ->where('reports.data.0.commit.parent', null)
            ->missing('reports.data.0.reporter_id'));
})->with([true, false]);

it('validates reasons and short details', function (array $data, string $field): void {
    $this->actingAs(User::factory()->create())->post(route('commit-report.store', Commit::factory()->create()), $data)->assertSessionHasErrors($field);
    expect(CommitReport::query()->count())->toBe(0);
})->with([
    [[], 'reason'],
    [['reason' => 'approve'], 'reason'],
    [['reason' => ['spam']], 'reason'],
    [['reason' => 'spam', 'details' => str_repeat('x', 501)], 'details'],
    [['reason' => 'spam', 'details' => ['private']], 'details'],
]);

it('keeps the first report and does not reopen a dismissed duplicate', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    $this->actingAs($user)->post(route('commit-report.store', $commit), ['reason' => 'spam'])->assertRedirect();
    $report = CommitReport::query()->sole();
    $report->forceFill(['dismissed_at' => now()])->save();
    $this->post(route('commit-report.store', $commit), ['reason' => 'other', 'details' => 'Changed'])->assertRedirect();
    expect(CommitReport::query()->count())->toBe(1)
        ->and($report->refresh()->reason)->toBe('spam')
        ->and($report->details)->toBeNull()
        ->and($report->dismissed_at)->not->toBeNull();
});

it('throttles reports per reporter including duplicates', function (): void {
    $commit = Commit::factory()->create();
    $this->actingAs(User::factory()->create());
    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('commit-report.store', $commit), ['reason' => 'spam'])->assertRedirect();
    }

    $this->postJson(route('commit-report.store', $commit), ['reason' => 'spam'])->assertTooManyRequests();
    $this->actingAs(User::factory()->create())->postJson(route('commit-report.store', $commit), ['reason' => 'spam'])->assertRedirect();
    expect(CommitReport::query()->count())->toBe(2);
});

it('restricts the reports queue and resolution to moderators including the reporter and author', function (): void {
    $report = CommitReport::factory()->create();
    $this->delete(route('commit-report.destroy', $report))->assertRedirectToRoute('home');
    foreach ([$report->reporter_id, $report->commit->user_id, User::factory()->create()->id] as $id) {
        $this->actingAs(User::query()->findOrFail($id))->get(route('reported-commit.index'))->assertForbidden();
        $this->delete(route('commit-report.destroy', $report))->assertForbidden();
        $this->post(route('commit-rejection.store', $report->commit))->assertForbidden();
    }

    expect($report->refresh()->dismissed_at)->toBeNull();
});

it('lists only unresolved reports on live published content oldest first without reporter identity', function (): void {
    $admin = User::factory()->create(['handle' => 'moderator-demo']);
    $older = CommitReport::factory()->create(['created_at' => now()->subHour(), 'details' => 'Review this context.']);
    $newer = CommitReport::factory()->create();
    CommitReport::factory()->create(['dismissed_at' => now()]);
    CommitReport::factory()->for(Commit::factory()->pending())->create();
    CommitReport::factory()->for(Commit::factory()->rejected())->create();
    $deleted = CommitReport::factory()->create();
    $deleted->commit->delete();

    $this->actingAs($admin)->get(route('reported-commit.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('moderation/reports')->has('reports.data', 2)
            ->where('reports.data.0.id', $older->id)->where('reports.data.1.id', $newer->id)
            ->where('reports.data.0.details', 'Review this context.')
            ->missing('reports.data.0.reporter_id')->missing('reports.data.0.reporter'));
});

it('does not expose hidden parent context in the reports queue', function (string $status): void {
    $parent = Commit::factory()->create(['status' => $status, 'body' => 'Hidden parent context']);
    CommitReport::factory()->for(Commit::factory()->for($parent, 'parent'))->create();
    $admin = User::factory()->create(['handle' => 'moderator-demo']);
    $this->actingAs($admin)->get(route('reported-commit.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('reports.data', 1)->where('reports.data.0.commit.parent', null));
})->with(['pending', 'rejected']);

it('paginates private reports without skipping equal timestamps', function (): void {
    $admin = User::factory()->create(['handle' => 'moderator-demo']);
    CommitReport::factory()->count(21)->create();
    $this->actingAs($admin)->get(route('reported-commit.index'))->assertOk();
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion()];
    $response = $this->getJson(route('reported-commit.index'), $headers);
    $response->assertOk()->assertJsonCount(20, 'props.reports.data');
    $ids = array_column($response->json('props.reports.data'), 'id');
    $next = $this->getJson(route('reported-commit.index', ['cursor' => $response->json('props.reports.next_cursor')]), $headers);
    $next->assertOk()->assertJsonCount(1, 'props.reports.data');
    expect($ids)->not->toContain($next->json('props.reports.data.0.id'));
});

it('does not accept spoofed ownership target or dismissal fields', function (): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();
    $this->actingAs($viewer)->post(route('commit-report.store', $commit), [
        'reason' => 'other',
        'reporter_id' => $commit->user_id,
        'commit_id' => Commit::factory()->pending()->create()->id,
        'dismissed_at' => now()->toISOString(),
    ])->assertRedirect();
    $report = CommitReport::query()->sole();
    expect($report->reporter_id)->toBe($viewer->id)
        ->and($report->commit_id)->toBe($commit->id)
        ->and($report->dismissed_at)->toBeNull();
});

it('dismisses idempotently without hiding the commit', function (): void {
    $report = CommitReport::factory()->create();
    $admin = User::factory()->create(['handle' => 'moderator-demo']);
    $this->actingAs($admin)->fromRoute('reported-commit.index')->delete(route('commit-report.destroy', $report))->assertRedirectToRoute('reported-commit.index');
    $this->delete(route('commit-report.destroy', $report))->assertRedirect();
    expect($report->refresh()->dismissed_at)->not->toBeNull()
        ->and($report->commit->status)->toBe(CommitStatus::Approved);
    $this->get(route('reported-commit.index'))->assertInertia(fn ($page) => $page->has('reports.data', 0));
    $this->actingAs(User::factory()->create())->get(route('commit.show', [$report->commit->user, $report->commit]))->assertOk();
});

it('rejects the original using existing moderation and removes all its reports from the queue', function (): void {
    $report = CommitReport::factory()->create();
    CommitReport::factory()->for($report->commit)->create();
    $admin = User::factory()->create(['handle' => 'moderator-demo']);
    $this->actingAs($admin)->fromRoute('reported-commit.index')->post(route('commit-rejection.store', $report->commit))->assertRedirectToRoute('reported-commit.index');
    expect($report->commit->refresh()->status)->toBe(CommitStatus::Rejected);
    $this->get(route('reported-commit.index'))->assertInertia(fn ($page) => $page->has('reports.data', 0));
    $this->delete(route('commit-report.destroy', $report))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('commit.show', [$report->commit->user, $report->commit]))->assertNotFound();
    $this->getJson(route('api-commit.show', $report->commit))->assertNotFound();
    $this->getJson(route('api-user-commit.index', $report->commit->user))->assertOk()->assertJsonCount(0, 'data');
    $this->get(route('user-feed.show', $report->commit->user))->assertOk()->assertDontSee($report->commit->body);
    $this->actingAs($report->commit->user)->get(route('commit.show', [$report->commit->user, $report->commit]))->assertOk();
});

it('does not resolve reports on deleted content', function (): void {
    $report = CommitReport::factory()->create();
    $report->commit->delete();
    $this->actingAs(User::factory()->create(['handle' => 'moderator-demo']))->delete(route('commit-report.destroy', $report))->assertNotFound();
});

it('cascades reports when the commit or either account is hard deleted', function (string $target): void {
    $report = CommitReport::factory()->create();
    match ($target) {
        'commit' => $report->commit->forceDelete(),
        'author' => $report->commit->user->delete(),
        default => User::query()->findOrFail($report->reporter_id)->delete(),
    };
    expect(CommitReport::query()->count())->toBe(0);
})->with(['commit', 'author', 'reporter']);

it('removes reports on account deletion tombstones while keeping reports on surviving replies', function (bool $alreadyDeleted): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    $reply = Commit::factory()->for($user)->for($root, 'parent')->create();
    $survivingReply = Commit::factory()->for($reply, 'parent')->create();
    $removedReport = CommitReport::factory()->for($reply)->create();
    $keptReport = CommitReport::factory()->for($survivingReply)->create();

    if ($alreadyDeleted) {
        $reply->delete();
    }

    $this->actingAs($user)->delete(route('user.destroy'))->assertRedirectToRoute('home');

    $tombstone = Commit::withTrashed()->findOrFail($reply->id);

    expect($tombstone->trashed())->toBeTrue()
        ->and($tombstone->user_id)->toBeNull()
        ->and($removedReport->fresh())->toBeNull()
        ->and(CommitReport::query()->sole()->id)->toBe($keptReport->id)
        ->and($survivingReply->fresh()->parent_id)->toBe($tombstone->id);

    $this->actingAs(User::factory()->create(['handle' => 'moderator-demo']))
        ->get(route('reported-commit.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('reports.data', 1)
            ->where('reports.data.0.id', $keptReport->id)
            ->where('reports.data.0.commit.parent', null)
            ->missing('reports.data.0.reporter_id'));
})->with([false, true]);

it('stores report screenshots encrypted and only serves them to moderators', function (): void {
    Storage::fake();
    $reporter = User::factory()->create();
    $commit = Commit::factory()->create();
    $this->actingAs($reporter)->post(route('commit-report.store', $commit), [
        'reason' => 'other',
        'images' => [UploadedFile::fake()->image('evidence.png', 200, 100)],
        'image_descriptions' => ['Evidence screenshot'],
    ])->assertRedirect();

    $report = CommitReport::query()->sole();
    expect($report->images)->toHaveCount(1);
    $file = $report->images[0];
    $bytes = Storage::disk()->get($file['path']);
    expect(str_starts_with($bytes, 'RIFF'))->toBeFalse();
    expect(str_starts_with(resolve(EncryptedFiles::class)->decrypt($bytes), 'RIFF'))->toBeTrue();

    $url = route('commit-report-image.show', ['report' => $report, 'image' => 0]);
    expect(DB::table('commit_reports')->where('id', $report->id)->value('images'))->not->toContain('Evidence screenshot', $file['path']);
    $this->get($url)->assertOk();
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    $this->actingAs($commit->user)->get($url)->assertNotFound();
    $this->actingAs(User::factory()->create(['handle' => 'moderator-demo']))->get($url)
        ->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('Cache-Control', 'no-store, private');
    $this->get(route('commit-report-image.show', ['report' => $report, 'image' => 1]))->assertNotFound();
    $this->get(route('reported-commit.index'))->assertInertia(fn ($page) => $page
        ->where('reports.data.0.attachments.0.url', $url)
        ->where('reports.data.0.attachments.0.description', 'Evidence screenshot')
        ->missing('reports.data.0.images'));

    $this->actingAs($reporter)->post(route('commit-report.store', $commit), [
        'reason' => 'spam', 'images' => [UploadedFile::fake()->image('duplicate.png')],
    ])->assertRedirect();
    expect(Storage::disk()->files('report-images'))->toHaveCount(1);
    expect($report->refresh()->images)->toHaveCount(1);
});

it('rejects invalid report images without storing a report', function (string $invalid): void {
    Storage::fake();
    $images = match ($invalid) {
        'type' => [UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')],
        'size' => [UploadedFile::fake()->image('large.png')->size(10241)],
        'count' => array_map(fn () => UploadedFile::fake()->image('image.png'), range(1, 4)),
    };
    $this->actingAs(User::factory()->create())->postJson(route('commit-report.store', Commit::factory()->create()), [
        'reason' => 'other', 'images' => $images,
    ])->assertUnprocessable();
    expect(CommitReport::query()->count())->toBe(0);
    expect(Storage::disk()->allFiles())->toBeEmpty();
})->with(['type', 'size', 'count']);
