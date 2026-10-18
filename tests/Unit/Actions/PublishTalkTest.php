<?php

declare(strict_types=1);

use App\Actions\CreateTalk;
use App\Actions\PublishTalk;
use App\Enums\TalkVisibility;
use App\Jobs\EmbedCommit;
use App\Jobs\ModerateCommit;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake([UnfurlLink::class, ModerateCommit::class, EmbedCommit::class]);
});

it('publishes a draft into the feed', function (): void {
    $user = User::factory()->create();
    $talk = resolve(CreateTalk::class)->handle(
        $user,
        'Ready now',
        'Was a draft.',
        now()->addWeek()->toImmutable(),
        slidesUrl: 'https://example.com/ready-now',
        visibility: TalkVisibility::Private,
    );

    $published = resolve(PublishTalk::class)->handle($talk);

    expect($published->isPublished())->toBeTrue()
        ->and($published->is_unlisted)->toBeFalse()
        ->and(Commit::query()->whereKey($published->commit_id)->exists())->toBeTrue();
});
