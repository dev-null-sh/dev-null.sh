<?php

declare(strict_types=1);

use App\Actions\UpdateMessagePin;
use App\Http\Requests\UpdateMessagePinRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

it('pins no more than an org holds when two pins pass the request at once', function (): void {
    $maintainer = User::factory()->create();
    [$member] = followersOf($maintainer, 1);
    $org = Conversation::query()->findOrFail(createOrg($maintainer, [$member]));
    Message::factory()->count(Conversation::MAX_PINNED_MESSAGES - 1)->pinned()->create(['conversation_id' => $org->id, 'user_id' => $member->id]);
    $first = pushToOrg($member, $org->id, 'first');
    $second = pushToOrg($member, $org->id, 'second');

    expect(UpdateMessagePinRequest::authorization($maintainer, $first)->allowed())->toBeTrue()
        ->and(UpdateMessagePinRequest::authorization($maintainer, $second)->allowed())->toBeTrue();

    $version = $org->fresh()?->reactions_version;

    resolve(UpdateMessagePin::class)->handle($first);
    resolve(UpdateMessagePin::class)->handle($second);

    expect($org->messages()->whereNotNull('pinned_at')->count())->toBe(Conversation::MAX_PINNED_MESSAGES)
        ->and($first->fresh()?->pinned_at)->not->toBeNull()
        ->and($second->fresh()?->pinned_at)->toBeNull()
        ->and($org->fresh()?->reactions_version)->toBe($version + 1);
});
