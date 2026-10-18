<?php

declare(strict_types=1);

use App\Actions\CreateTalk;
use App\Enums\TalkLinkKind;
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
        'https://speakerdeck.com/example/shipping-faster',
        TalkLinkKind::Slides,
        'Laracon',
    );

    expect($talk)
        ->toBeInstanceOf(Talk::class)
        ->and($talk->title)->toBe('Shipping faster with Pest')
        ->and($talk->event_name)->toBe('Laracon')
        ->and($talk->link_kind)->toBe(TalkLinkKind::Slides)
        ->and($talk->isPublished())->toBeTrue()
        ->and($talk->commit_id)->not->toBeNull();

    $commit = Commit::query()->findOrFail($talk->commit_id);

    expect($commit->user_id)->toBe($user->id)
        ->and($commit->body)->toContain('Shipping faster with Pest')
        ->and($commit->body)->toContain('https://speakerdeck.com/example/shipping-faster')
        ->and($commit->parent_id)->toBeNull();
});
