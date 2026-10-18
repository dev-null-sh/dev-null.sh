<?php

declare(strict_types=1);

use App\Actions\CreateTalk;
use App\Enums\TalkVisibility;
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

it('creates a talk and publishes an anchor commit for the feed', function (): void {
    $user = User::factory()->create();

    $talk = resolve(CreateTalk::class)->handle(
        $user,
        'Shipping faster with Pest',
        'A practical talk about testing habits that stick.',
        now()->subDay()->toImmutable(),
        slidesUrl: 'https://speakerdeck.com/example/shipping-faster',
        recordingUrl: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        eventName: 'Laracon',
    );

    expect($talk)
        ->toBeInstanceOf(Talk::class)
        ->and($talk->title)->toBe('Shipping faster with Pest')
        ->and($talk->event_name)->toBe('Laracon')
        ->and($talk->slides_url)->toBe('https://speakerdeck.com/example/shipping-faster')
        ->and($talk->recording_url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and($talk->isPublished())->toBeTrue()
        ->and($talk->is_unlisted)->toBeFalse()
        ->and($talk->commit_id)->not->toBeNull();

    $commit = Commit::query()->findOrFail($talk->commit_id);

    expect($commit->user_id)->toBe($user->id)
        ->and($commit->body)->toContain('Shipping faster with Pest')
        ->and($commit->body)->toContain('https://speakerdeck.com/example/shipping-faster')
        ->and($commit->body)->toContain('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and($commit->parent_id)->toBeNull();
});

it('saves a private draft without an anchor commit', function (): void {
    $user = User::factory()->create();

    $talk = resolve(CreateTalk::class)->handle(
        $user,
        'Upcoming deck',
        'Slides first, recording later.',
        now()->addMonth()->toImmutable(),
        slidesUrl: 'https://example.com/upcoming-deck',
        visibility: TalkVisibility::Private,
    );

    expect($talk->isDraft())->toBeTrue()
        ->and($talk->commit_id)->toBeNull()
        ->and($talk->published_at)->toBeNull()
        ->and(Commit::query()->count())->toBe(0);
});

it('publishes an unlisted talk', function (): void {
    $user = User::factory()->create();

    $talk = resolve(CreateTalk::class)->handle(
        $user,
        'Quiet talk',
        'Link only.',
        now()->subDay()->toImmutable(),
        recordingUrl: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        visibility: TalkVisibility::Unlisted,
    );

    expect($talk->isPublished())->toBeTrue()
        ->and($talk->is_unlisted)->toBeTrue()
        ->and($talk->isDiscoverable())->toBeFalse();
});
