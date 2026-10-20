<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('lets members push, read, and see who pulled', function (): void {
    $mark = User::factory()->create();
    [$nuno, $taylor] = followersOf($mark, 2);
    $org = createOrg($mark, [$nuno, $taylor]);
    $message = pushToOrg($mark, $org, 'ship it?');

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonPath('conversations.0.ahead', 1)->assertJsonPath('unread', 1);
    $this->putJson(route('org-read.update', $org), ['through' => $message->id])->assertNoContent();
    $this->getJson(route('inbox.index'))->assertJsonPath('unread', 0);

    $members = collect($this->actingAs($mark)->getJson(route('org-message.index', $org))->assertOk()->json('members'))
        ->mapWithKeys(fn (array $member): array => [$member['user']['username'] => $member['read_through_id']]);

    expect($members[$nuno->username])->toBe($message->id)
        ->and($members[$taylor->username])->toBeLessThan($message->id);
});

it('hides pulls from people who do not share them', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $nuno->update(['pull_receipts' => false]);
    $org = createOrg($mark, [$nuno]);
    $message = pushToOrg($mark, $org);
    $this->actingAs($nuno)->putJson(route('org-read.update', $org), ['through' => $message->id]);

    $this->actingAs($mark)->getJson(route('org-message.index', $org))->assertJsonPath('members.1.read_through_id', 0);
});

it('shows who is typing', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);

    $this->actingAs($nuno)->putJson(route('org-typing.update', $org))->assertNoContent();

    $this->actingAs($mark)->getJson(route('org-message.index', $org))->assertJsonPath('typing', [$nuno->username]);
    $this->actingAs($nuno)->getJson(route('org-message.index', $org))->assertJsonPath('typing', []);
});

it('checks all org typing indicators in one cache read', function (): void {
    $mark = User::factory()->create();
    [$nuno, $taylor, $quiet] = followersOf($mark, 3);
    $org = createOrg($mark, [$nuno, $taylor, $quiet]);

    $this->actingAs($nuno)->putJson(route('org-typing.update', $org))->assertNoContent();
    $this->actingAs($taylor)->putJson(route('org-typing.update', $org))->assertNoContent();

    $cache = Cache::store();
    Cache::shouldReceive('many')
        ->once()
        ->andReturnUsing(fn (array $keys): array => $cache->many($keys));

    $this->actingAs($mark)
        ->getJson(route('org-message.index', $org))
        ->assertOk()
        ->assertJsonPath('typing', [$nuno->username, $taylor->username]);
});

it('stashes an org for one member', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);

    $this->actingAs($nuno)->postJson(route('org-archive.store', $org))->assertNoContent();
    $this->getJson(route('inbox.index', ['tab' => 'stashed']))->assertJsonCount(1, 'conversations');
    $this->deleteJson(route('org-archive.destroy', $org))->assertNoContent();
    $this->getJson(route('inbox.index'))->assertJsonCount(1, 'conversations');
});

it('finds orgs by name or by a member', function (string $term): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $nuno->update(['username' => 'nunomaduro']);
    createOrg($mark, [$nuno], name: 'polling crew');

    $this->actingAs($mark)->getJson(route('inbox.index', ['q' => $term]))->assertJsonCount(1, 'conversations');
})->with(['polling', 'nunomad']);

it('keeps non-members out', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);

    $this->actingAs(User::factory()->create())->postJson(route('org-message.store', $org), ['body' => 'hi', 'client_id' => fake()->uuid()])->assertNotFound();
});

it('replies only to messages in the history the member can see', function (): void {
    $mark = User::factory()->create();
    [$nuno, $taylor] = followersOf($mark, 2);
    $org = createOrg($mark, [$nuno]);
    $before = pushToOrg($nuno, $org, 'from before taylor joined');
    $this->actingAs($mark)->postJson(route('org-member.store', $org), ['members' => [$taylor->username], 'share_history' => false])->assertNoContent();
    $after = pushToOrg($nuno, $org, 'welcome taylor');

    $this->actingAs($taylor)->postJson(route('org-message.store', $org), ['body' => 'hi', 'client_id' => Str::uuid()->toString(), 'reply_to_id' => $before->id])
        ->assertUnprocessable()->assertJsonValidationErrors('reply_to_id');

    $this->postJson(route('org-message.store', $org), ['body' => 'thanks!', 'client_id' => Str::uuid()->toString(), 'reply_to_id' => $after->id])
        ->assertCreated()
        ->assertJsonPath('message.reply_to.user_id', $nuno->id)
        ->assertJsonPath('message.reply_to.body', 'welcome taylor');
});

it('pushes images and the link card the member chose', function (?string $choice, ?string $url): void {
    Queue::fake();
    Storage::fake();

    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);

    $this->actingAs($nuno)->post(route('org-message.store', $org), [
        'body' => 'release notes at https://laravel.com/docs and https://github.com/laravel',
        'client_id' => Str::uuid()->toString(),
        'link_preview_url' => $choice,
        'images' => [UploadedFile::fake()->image('notes.png', 20, 20)],
    ], ['Accept' => 'application/json'])->assertCreated()
        ->assertJsonPath('message.link_preview.url', $url)
        ->assertJsonCount(1, 'message.images');
})->with([
    'another link' => ['https://github.com/laravel', 'https://github.com/laravel'],
    'no card' => ['', null],
]);
