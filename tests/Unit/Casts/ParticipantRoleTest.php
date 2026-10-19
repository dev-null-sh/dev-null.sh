<?php

declare(strict_types=1);

use App\Enums\BroadcastRole;
use App\Enums\OrgRole;
use App\Models\ConversationParticipant;

it('reads nullable direct, org and broadcast roles from their shared column', function (?string $stored, OrgRole|BroadcastRole|null $expected): void {
    $participant = new ConversationParticipant();
    $participant->setRawAttributes(['role' => $stored]);

    expect($participant->role)->toBe($expected);
})->with([
    'direct' => [null, null],
    'maintainer' => ['maintainer', OrgRole::Maintainer],
    'contributor' => ['contributor', OrgRole::Contributor],
    'owner' => ['owner', BroadcastRole::Owner],
    'publisher' => ['publisher', BroadcastRole::Publisher],
    'subscriber' => ['subscriber', BroadcastRole::Subscriber],
]);
