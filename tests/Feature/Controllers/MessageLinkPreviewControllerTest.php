<?php

declare(strict_types=1);

use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
});

it('returns the link card of a message to the people in the conversation', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $message = sendChatMessage($mark, $nuno, 'docs at https://laravel.com/docs');
    LinkPreview::query()->sole()->forceFill(['title' => 'Laravel docs', 'fetched_at' => now()])->save();

    foreach ([$mark, $nuno] as $participant) {
        $this->actingAs($participant)->getJson(route('message-link-preview.show', $message))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('link_preview.title', 'Laravel docs')
            ->assertJsonPath('link_preview.is_pending', false);
    }

    $this->actingAs(User::factory()->create())->getJson(route('message-link-preview.show', $message))->assertNotFound();
});

it('keeps link cards out of the history an org member cannot see', function (): void {
    $mark = User::factory()->create();
    [$nuno, $taylor] = followersOf($mark, 2);
    $org = createOrg($mark, [$nuno]);
    $before = pushToOrg($nuno, $org, 'see https://laravel.com');
    $this->actingAs($mark)->postJson(route('org-member.store', $org), ['members' => [$taylor->handle], 'share_history' => false])->assertNoContent();

    $this->actingAs($taylor)->getJson(route('message-link-preview.show', $before))->assertNotFound();
});
