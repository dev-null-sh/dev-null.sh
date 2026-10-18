<?php

declare(strict_types=1);

use App\Actions\CreateTalk;
use App\Enums\CommitStatus;
use App\Enums\Log;
use App\Jobs\EmbedCommit;
use App\Jobs\ModerateCommit;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\Talk;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake([UnfurlLink::class, ModerateCommit::class, EmbedCommit::class]);
});

it('lets a user create a talk with title, description, date, and links', function (): void {
    $user = User::factory()->create(['username' => 'speaker']);

    $this->actingAs($user)->post(route('talk.store'), [
        'title' => 'Hexagonal Laravel',
        'description' => 'Boundaries that keep apps maintainable.',
        'presented_on' => now()->subDays(2)->toDateString(),
        'recording_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'slides_url' => 'https://speakerdeck.com/example/hexagonal-laravel',
        'event_name' => 'PHP Benelux',
    ])->assertRedirect(route('user.show', [$user, 'tab' => 'talks']));

    $talk = Talk::query()->sole();

    expect($talk->title)->toBe('Hexagonal Laravel')
        ->and($talk->description)->toBe('Boundaries that keep apps maintainable.')
        ->and($talk->recording_url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and($talk->slides_url)->toBe('https://speakerdeck.com/example/hexagonal-laravel')
        ->and($talk->isPublished())->toBeTrue();

    $this->get(route('talk.show', [$user, $talk]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('talk/show')
            ->where('talk.title', 'Hexagonal Laravel')
            ->where('talk.recording_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->where('talk.slides_url', 'https://speakerdeck.com/example/hexagonal-laravel')
            ->where('talk.recording_embed.provider', 'YouTube')
            ->has('commit')
            ->has('replies'));
});

it('embeds recordings on the talks explorer list', function (): void {
    $user = User::factory()->create();
    $talk = resolve(CreateTalk::class)->handle(
        $user,
        'List preview',
        'Should embed on /talks.',
        now()->subDay()->toImmutable(),
        recordingUrl: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    );
    Commit::query()->whereKey($talk->commit_id)->update(['status' => CommitStatus::Approved]);

    $this->actingAs($user)
        ->get(route('talk.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('talks/index')
            ->where('talks.data.0.id', $talk->id)
            ->where('talks.data.0.recording_embed.provider', 'YouTube'));
});

it('shows published talks on the author profile talks tab', function (): void {
    $user = User::factory()->create(['username' => 'speaker']);
    $viewer = User::factory()->create();

    $talk = resolve(CreateTalk::class)->handle(
        $user,
        'Visible talk',
        'Everyone should see this on the profile.',
        now()->subWeek()->toImmutable(),
        slidesUrl: 'https://example.com/visible-talk',
    );

    Commit::query()->whereKey($talk->commit_id)->update(['status' => CommitStatus::Approved]);

    $this->actingAs($viewer)
        ->get(route('user.show', [$user, 'tab' => 'talks']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/show')
            ->where('tab', 'talks')
            ->where('talks.data.0.id', $talk->id)
            ->where('talks.data.0.title', 'Visible talk'));
});

it('lets other users comment on a published talk through its anchor commit', function (): void {
    $speaker = User::factory()->create(['username' => 'speaker']);
    $viewer = User::factory()->create();

    $talk = resolve(CreateTalk::class)->handle(
        $speaker,
        'Feedback welcome',
        'Please leave notes under the talk.',
        now()->subDays(3)->toImmutable(),
        slidesUrl: 'https://example.com/feedback-talk',
    );

    $commit = Commit::query()->findOrFail($talk->commit_id);
    $commit->forceFill(['status' => CommitStatus::Approved])->save();

    $this->actingAs($viewer)
        ->postJson(route('commit-reply.store', $commit), [
            'body' => 'Great talk, thanks for sharing the slides.',
        ])
        ->assertCreated();

    expect(Commit::query()->where('parent_id', $commit->id)->sole()->body)
        ->toBe('Great talk, thanks for sharing the slides.');
});

it('puts the talk anchor commit into the activity feed when published', function (): void {
    $speaker = User::factory()->create();
    $follower = User::factory()->create();
    $follower->following()->attach($speaker);

    $talk = resolve(CreateTalk::class)->handle(
        $speaker,
        'Feed talk',
        'This should land in following.',
        now()->subDay()->toImmutable(),
        slidesUrl: 'https://example.com/feed-talk',
    );

    Commit::query()->whereKey($talk->commit_id)->update(['status' => CommitStatus::Approved]);

    $this->actingAs($follower)
        ->get(route('log.show', Log::Following))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.id', $talk->commit_id));
});

it('validates talk creation before the action runs', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('talk.store'), [
        'title' => 'ab',
        'description' => '',
        'presented_on' => 'not-a-date',
        'recording_url' => 'not-a-url',
        'slides_url' => 'ftp://example.com/deck',
    ])->assertSessionHasErrors(['title', 'description', 'presented_on', 'recording_url', 'slides_url']);

    expect(Talk::query()->count())->toBe(0)
        ->and(Commit::query()->count())->toBe(0);
});

it('requires at least one of slides or recording', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('talk.store'), [
        'title' => 'Missing links',
        'description' => 'Needs at least one media URL.',
        'presented_on' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors(['slides_url', 'recording_url']);

    expect(Talk::query()->count())->toBe(0);
});

it('lets the author delete their talk', function (): void {
    $user = User::factory()->create();
    $talk = resolve(CreateTalk::class)->handle(
        $user,
        'Temporary talk',
        'Going away soon.',
        now()->subDay()->toImmutable(),
        slidesUrl: 'https://example.com/temporary-talk',
    );

    $this->actingAs($user)
        ->delete(route('talk.destroy', $talk))
        ->assertRedirect(route('user.show', [$user, 'tab' => 'talks']));

    expect(Talk::query()->whereKey($talk->id)->exists())->toBeFalse();
});
