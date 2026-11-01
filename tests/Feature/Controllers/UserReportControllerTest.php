<?php

declare(strict_types=1);

use App\Enums\TimeoutDuration;
use App\Models\Message;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Facades\DB;
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

it('answers not found for someone who does not exist or who blocked the reporter', function (): void {
    $reporter = User::factory()->create();
    $blocker = User::factory()->create();
    $blocker->blocking()->attach($reporter);

    $this->actingAs($reporter)->post(route('user-report.store', $blocker), ['reason' => 'spam'])->assertNotFound();
    $this->actingAs($reporter)->post('/@nobody-has-this-handle/reports', ['reason' => 'spam'])->assertNotFound();

    expect(UserReport::query()->count())->toBe(0);
});

it('still lets someone report the person they blocked, even when both blocked each other', function (bool $mutual): void {
    $reporter = User::factory()->create();
    $user = User::factory()->create();
    $reporter->blocking()->attach($user);

    if ($mutual) {
        $user->blocking()->attach($reporter);
    }

    $this->actingAs($reporter)->post(route('user-report.store', $user), ['reason' => 'harassment'])->assertRedirect();

    expect(UserReport::query()->sole()->user_id)->toBe($user->id);
})->with([false, true]);

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

it('lists open reports oldest first, with what moderators can do, without the reporter or any message', function (): void {
    $moderator = reportModerator();
    $newer = UserReport::factory()->create(['reason' => 'child_safety', 'details' => 'Please look', 'created_at' => now()]);
    $older = UserReport::factory()->create(['created_at' => now()->subHour()]);
    UserReport::factory()->create(['dismissed_at' => now()]);
    UserReport::factory()->create(['user_id' => User::factory()->create(['banned_at' => now()])->id]);
    Message::factory()->create(['user_id' => $newer->user_id, 'body' => 'Private words']);

    $this->actingAs($moderator)->get(route('reported-user.index'))->assertOk()
        ->assertDontSee('Private words')
        ->assertDontSee($newer->reporter_id)
        ->assertInertia(fn ($page) => $page
            ->component('moderation/profile-reports')
            ->where('reasons.child_safety', 'Child safety, such as grooming or sexual content involving a minor')
            ->has('reports.data', 2)
            ->where('reports.data.0.id', $older->id)
            ->where('reports.data.1.id', $newer->id)
            ->where('reports.data.1.reason', 'child_safety')
            ->where('reports.data.1.details', 'Please look')
            ->where('reports.data.1.user.handle', $newer->user->handle)
            ->where('reports.data.1.moderation.timeoutsCount', 0)
            ->missing('reports.data.1.reporter_id')
            ->missing('reports.data.1.body'));
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
