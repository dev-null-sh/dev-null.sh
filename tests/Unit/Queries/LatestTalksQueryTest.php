<?php

declare(strict_types=1);

use App\Actions\CreateTalk;
use App\Enums\CommitStatus;
use App\Enums\TalkLinkKind;
use App\Jobs\EmbedCommit;
use App\Jobs\ModerateCommit;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\User;
use App\Queries\LatestTalksQuery;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake([UnfurlLink::class, ModerateCommit::class, EmbedCommit::class]);
});

it('lists recent published talks with approved anchors', function (): void {
    $speaker = User::factory()->create(['username' => 'speaker']);
    $viewer = User::factory()->create();

    $talk = resolve(CreateTalk::class)->handle(
        $speaker,
        'Explore me',
        'A talk for the aside explorer.',
        now()->subDay()->toImmutable(),
        'https://example.com/explore-me',
        TalkLinkKind::Slides,
        'Laracon',
    );

    Commit::query()->whereKey($talk->commit_id)->update(['status' => CommitStatus::Approved]);

    $pending = resolve(CreateTalk::class)->handle(
        $speaker,
        'Still pending',
        'Should stay out of the explorer.',
        now()->subHours(2)->toImmutable(),
        'https://example.com/pending-talk',
        TalkLinkKind::Recording,
    );

    $talks = resolve(LatestTalksQuery::class)->get($viewer);

    expect($talks)->toHaveCount(1)
        ->and($talks->first()['id'])->toBe($talk->id)
        ->and($talks->first()['title'])->toBe('Explore me')
        ->and($talks->first()['user']['username'])->toBe('speaker')
        ->and($talks->pluck('id'))->not->toContain($pending->id);
});
