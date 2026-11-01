<?php

declare(strict_types=1);

use App\Enums\TimeoutDuration;
use App\Mail\ChildSafetyReportEmail;
use App\Models\Message;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['app.admins' => ['moderator-demo']]);
});

function reportModerator(): User
{
    return User::factory()->create(['handle' => 'moderator-demo']);
}

it('privately reports a person, without telling them or blocking them', function (): void {
    $reporter = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($reporter)->from('/remotes')
        ->post(route('user-report.store', $user), ['reason' => 'child_safety', 'details' => 'They asked my age.'])
        ->assertRedirect('/remotes')
        ->assertSessionHasNoErrors();

    expect(UserReport::query()->sole())
        ->user_id->toBe($user->id)
        ->reporter_id->toBe($reporter->id)
        ->reason->toBe('child_safety')
        ->details->toBe('They asked my age.')
        ->and($reporter->isBlocking($user))->toBeFalse();

    $this->actingAs($user)->get(route('user.show', $reporter))->assertOk()
        ->assertInertia(fn ($page) => $page->missing('reports')->missing('user.reports'));
});

it('encrypts the details at rest', function (): void {
    $this->actingAs(User::factory()->create())
        ->post(route('user-report.store', User::factory()->create()), ['reason' => 'other', 'details' => 'Something private']);

    expect(DB::table('user_reports')->value('details'))->not->toContain('Something private')
        ->and(UserReport::query()->sole()->details)->toBe('Something private');
});

it('accepts each reason, with details optional', function (string $reason): void {
    $this->actingAs(User::factory()->create())
        ->post(route('user-report.store', User::factory()->create()), ['reason' => $reason, 'details' => '   '])
        ->assertRedirect();

    expect(UserReport::query()->sole())->reason->toBe($reason)->details->toBeNull();
})->with(['spam', 'harassment', 'unsafe', 'child_safety', 'other']);

it('requires authentication', function (): void {
    $this->post(route('user-report.store', User::factory()->create()), ['reason' => 'spam'])->assertRedirect();

    expect(UserReport::query()->count())->toBe(0);
});

it('rejects reporting yourself', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('user-report.store', $user), ['reason' => 'spam'])->assertForbidden();

    expect(UserReport::query()->count())->toBe(0);
});

it('answers not found for someone who does not exist', function (): void {
    $this->actingAs(User::factory()->create())->post('/@nobody-has-this-handle/reports', ['reason' => 'spam'])->assertNotFound();

    expect(UserReport::query()->count())->toBe(0);
});

it('reports across a block in either direction, with the same answer as without one', function (string $block): void {
    $reporter = User::factory()->create();
    $user = User::factory()->create();

    if (in_array($block, ['reporter', 'both'], true)) {
        $reporter->blocking()->attach($user);
    }

    if (in_array($block, ['reported', 'both'], true)) {
        $user->blocking()->attach($reporter);
    }

    $this->actingAs($reporter)->from('/remotes')
        ->post(route('user-report.store', $user), ['reason' => 'harassment'])
        ->assertRedirect('/remotes')
        ->assertSessionHasNoErrors();

    expect(UserReport::query()->sole()->user_id)->toBe($user->id);
})->with(['reporter', 'reported', 'both', 'neither']);

it('gives the page for someone who blocked the viewer the handle to report them by, and nothing else about them', function (): void {
    $viewer = User::factory()->create();
    $blocker = User::factory()->create();
    $blocker->blocking()->attach($viewer);

    $this->actingAs($viewer)->get(route('user.show', $blocker))->assertOk()
        ->assertInertia(fn ($page) => $page->component('user/unavailable')->where('handle', $blocker->handle)->where('blockedUser', null));
});

it('validates the reason and short details', function (array $data, string $field): void {
    $this->actingAs(User::factory()->create())
        ->post(route('user-report.store', User::factory()->create()), $data)
        ->assertSessionHasErrors($field);

    expect(UserReport::query()->count())->toBe(0);
})->with([
    'no reason' => [[], 'reason'],
    'unknown reason' => [['reason' => 'approve'], 'reason'],
    'details too long' => [['reason' => 'spam', 'details' => Str::repeat('x', 501)], 'details'],
]);

it('keeps the first report while it is open, and takes a new one once it was dismissed', function (): void {
    $reporter = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'spam']);
    $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'other'])->assertRedirect();

    $first = UserReport::query()->sole();
    expect($first->reason)->toBe('spam');

    $this->actingAs(reportModerator())->delete(route('user-report.destroy', $first))->assertRedirect();
    $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'child_safety'])->assertRedirect();

    expect(UserReport::query()->count())->toBe(2)
        ->and(UserReport::query()->whereNull('dismissed_at')->sole()->reason)->toBe('child_safety');
});

it('raises an open report for child safety, keeping the old details and the new ones', function (?string $old, ?string $new, ?string $kept): void {
    Mail::fake();
    reportModerator();
    $reporter = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'spam', 'details' => $old]);
    $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'child_safety', 'details' => $new])->assertRedirect();

    expect(UserReport::query()->sole())->reason->toBe('child_safety')->details->toBe($kept);
    Mail::assertQueuedCount(1);
})->with([
    'both' => ['Links', 'They asked my age.', "Links\n\nThey asked my age."],
    'old only' => ['Links', null, 'Links'],
    'new only' => [null, 'They asked my age.', 'They asked my age.'],
    'neither' => [null, null, null],
    'the same words' => ['Links', 'Links', 'Links'],
]);

it('never lowers a child safety report, and adds new details without another alert', function (): void {
    Mail::fake();
    reportModerator();
    $reporter = User::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'child_safety', 'details' => 'First']);
    Mail::assertQueuedCount(1);

    $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'spam', 'details' => 'Dropped']);
    expect(UserReport::query()->sole())->reason->toBe('child_safety')->details->toBe('First');

    $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'child_safety', 'details' => 'Second']);
    expect(UserReport::query()->sole())->reason->toBe('child_safety')->details->toBe("First\n\nSecond");
    Mail::assertQueuedCount(1);
});

it('raises the open report, not an older dismissed one', function (): void {
    $reporter = User::factory()->create();
    $user = User::factory()->create();
    $dismissed = UserReport::factory()->create(['user_id' => $user->id, 'reporter_id' => $reporter->id, 'dismissed_at' => now()]);
    $open = UserReport::factory()->create(['user_id' => $user->id, 'reporter_id' => $reporter->id]);

    $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'child_safety'])->assertRedirect();

    expect($open->refresh()->reason)->toBe('child_safety')
        ->and($dismissed->refresh()->reason)->toBe('spam');
});

it('alerts every moderator of a new child safety report, with nothing about it', function (): void {
    Mail::fake();
    $moderator = reportModerator();
    config(['app.admins' => ['moderator-demo', 'second-moderator', 'no-email-moderator']]);
    $second = User::factory()->create(['handle' => 'second-moderator']);
    User::factory()->create(['handle' => 'no-email-moderator', 'email' => null]);
    $reporter = User::factory()->create(['name' => 'Rae Reporter']);
    $user = User::factory()->create(['name' => 'Tess Target']);

    $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'child_safety', 'details' => 'Secret words']);

    Mail::assertQueuedCount(2);
    Mail::assertQueued(ChildSafetyReportEmail::class, fn (ChildSafetyReportEmail $mail): bool => $mail->hasTo($moderator->email));
    Mail::assertQueued(ChildSafetyReportEmail::class, function (ChildSafetyReportEmail $mail) use ($second, $reporter, $user): bool {
        $mail->assertHasSubject('A child safety report needs review')
            ->assertSeeInHtml(route('reported-user.index'))
            ->assertDontSeeInHtml('Secret words')
            ->assertDontSeeInHtml($reporter->handle)
            ->assertDontSeeInHtml($reporter->name)
            ->assertDontSeeInHtml($user->handle)
            ->assertDontSeeInHtml($user->name);

        return $mail->hasTo($second->email);
    });
});

it('does not alert for other reasons', function (): void {
    Mail::fake();
    reportModerator();

    $this->actingAs(User::factory()->create())->post(route('user-report.store', User::factory()->create()), ['reason' => 'harassment']);

    Mail::assertNothingQueued();
});

it('logs when no moderator can be alerted, and keeps the report', function (): void {
    Mail::fake();
    Log::spy();

    $this->actingAs(User::factory()->create())->post(route('user-report.store', User::factory()->create()), ['reason' => 'child_safety'])->assertRedirect();

    Mail::assertNothingQueued();
    Log::shouldHaveReceived('warning')->once()->with('No moderator has an email address to be alerted about a child safety report.');
    expect(UserReport::query()->count())->toBe(1);
});

it('keeps the report when the alert cannot be queued', function (): void {
    reportModerator();
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('The queue is down.'));

    $this->actingAs(User::factory()->create())->post(route('user-report.store', User::factory()->create()), ['reason' => 'child_safety'])->assertRedirect();

    expect(UserReport::query()->count())->toBe(1);
});

it('throttles reports per reporter', function (): void {
    $reporter = User::factory()->create();
    $user = User::factory()->create();

    foreach (range(1, 5) as $ignored) {
        $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'spam'])->assertRedirect();
    }

    $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'spam'])->assertTooManyRequests();
});

it('restricts the queue and dismissal to moderators, including the reporter and the person reported', function (): void {
    $report = UserReport::factory()->create();

    foreach ([$report->user, User::query()->findOrFail($report->reporter_id)] as $user) {
        $this->actingAs($user)->get(route('reported-user.index'))->assertForbidden();
        $this->actingAs($user)->delete(route('user-report.destroy', $report))->assertForbidden();
    }

    expect($report->refresh()->dismissed_at)->toBeNull();
});

it('lists open child safety reports first and then oldest first, with what moderators can do, without the reporter or any message', function (): void {
    $moderator = reportModerator();
    $newer = UserReport::factory()->create(['reason' => 'child_safety', 'details' => 'Please look', 'created_at' => now()]);
    $older = UserReport::factory()->create(['created_at' => now()->subHour()]);
    $middle = UserReport::factory()->create(['created_at' => now()->subMinute()]);
    UserReport::factory()->create(['dismissed_at' => now()]);
    UserReport::factory()->create(['user_id' => User::factory()->create(['banned_at' => now()])->id]);
    Message::factory()->create(['user_id' => $newer->user_id, 'body' => 'Private words']);

    $this->actingAs($moderator)->get(route('reported-user.index'))->assertOk()
        ->assertDontSee('Private words')
        ->assertDontSee($newer->reporter_id)
        ->assertInertia(fn ($page) => $page
            ->component('moderation/profile-reports')
            ->where('reasons.child_safety', 'Child safety, such as grooming or sexual content involving a minor')
            ->has('reports.data', 3)
            ->where('reports.data.0.id', $newer->id)
            ->where('reports.data.1.id', $older->id)
            ->where('reports.data.2.id', $middle->id)
            ->where('reports.data.0.reason', 'child_safety')
            ->where('reports.data.0.details', 'Please look')
            ->where('reports.data.0.user.handle', $newer->user->handle)
            ->where('reports.data.0.moderation.timeoutsCount', 0)
            ->missing('reports.data.0.reporter_id')
            ->missing('reports.data.0.body'));
});

it('gives no moderation menu on a report about an admin', function (): void {
    $moderator = reportModerator();
    UserReport::factory()->create(['user_id' => $moderator->id]);

    $this->actingAs($moderator)->get(route('reported-user.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('reports.data.0.moderation', null));
});

it('does not query per report', function (): void {
    $moderator = reportModerator();
    UserReport::factory()->create();
    $this->actingAs($moderator)->get(route('reported-user.index'))->assertOk();

    DB::enableQueryLog();
    $this->actingAs($moderator)->get(route('reported-user.index'))->assertOk();
    $one = count(DB::getQueryLog());

    UserReport::factory()->count(5)->create();

    DB::flushQueryLog();
    $this->actingAs($moderator)->get(route('reported-user.index'))->assertOk();

    expect(count(DB::getQueryLog()))->toBe($one);
});

it('paginates without skipping equal timestamps', function (): void {
    $moderator = reportModerator();
    $reports = UserReport::factory()->count(21)->create(['created_at' => now()]);

    $first = $this->actingAs($moderator)->get(route('reported-user.index'))->assertOk()->inertiaProps('reports');
    expect($first['data'])->toHaveCount(20)->and($first['next_cursor'])->not->toBeNull();

    $second = $this->actingAs($moderator)->get(route('reported-user.index', ['cursor' => $first['next_cursor']]))->assertOk()->inertiaProps('reports');

    expect(collect([...$first['data'], ...$second['data']])->pluck('id')->sort()->values()->all())
        ->toBe($reports->pluck('id')->sort()->values()->all());
});

it('paginates across child safety reports and the rest without skipping any', function (): void {
    $moderator = reportModerator();
    $reports = UserReport::factory()->count(15)->create(['created_at' => now()])
        ->concat(UserReport::factory()->count(15)->create(['reason' => 'child_safety', 'created_at' => now()]));

    $first = $this->actingAs($moderator)->get(route('reported-user.index'))->assertOk()->inertiaProps('reports');
    $second = $this->actingAs($moderator)->get(route('reported-user.index', ['cursor' => $first['next_cursor']]))->assertOk()->inertiaProps('reports');
    $all = collect([...$first['data'], ...$second['data']]);

    expect($all->pluck('id')->sort()->values()->all())->toBe($reports->pluck('id')->sort()->values()->all())
        ->and($all->take(15)->pluck('reason')->unique()->all())->toBe(['child_safety']);
});

it('dismisses idempotently, and only moves the report out of the queue', function (): void {
    $moderator = reportModerator();
    $report = UserReport::factory()->create();

    $this->actingAs($moderator)->from(route('reported-user.index'))->delete(route('user-report.destroy', $report))->assertRedirect(route('reported-user.index'));
    $dismissedAt = $report->refresh()->dismissed_at;
    expect($dismissedAt)->not->toBeNull();

    $this->travel(1)->minute();
    $this->actingAs($moderator)->delete(route('user-report.destroy', $report))->assertRedirect();
    expect($report->refresh()->dismissed_at->equalTo($dismissedAt))->toBeTrue()
        ->and($report->user->isBanned())->toBeFalse();
});

it('takes a report out of the queue when the person is banned', function (): void {
    $moderator = reportModerator();
    $report = UserReport::factory()->create();

    $this->actingAs($moderator)->post(route('user-ban.store', $report->user), ['reason' => 'unsafe'])->assertRedirect();

    $this->actingAs($moderator)->get(route('reported-user.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('reports.data', 0));
    $this->actingAs($moderator)->delete(route('user-report.destroy', $report))->assertNotFound();
});

it('keeps a report in the queue when the person is put in timeout', function (): void {
    $moderator = reportModerator();
    $report = UserReport::factory()->create();

    $this->actingAs($moderator)->post(route('user-timeout.store', $report->user), ['reason' => 'harassment', 'duration' => array_key_first(TimeoutDuration::options())])->assertRedirect();

    $this->actingAs($moderator)->get(route('reported-user.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('reports.data.0.id', $report->id)->whereNot('reports.data.0.moderation.timeout', null));
});

it('cascades reports when either account is deleted', function (string $target): void {
    $report = UserReport::factory()->create();

    User::query()->findOrFail($target === 'user' ? $report->user_id : $report->reporter_id)->delete();

    expect(UserReport::query()->count())->toBe(0);
})->with(['user', 'reporter']);
