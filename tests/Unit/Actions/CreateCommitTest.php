<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Enums\CommitStatus;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('may create a commit', function (): void {
    $user = User::factory()->create();

    $action = resolve(CreateCommit::class);

    $commit = $action->handle($user, 'Hello, devnull!');

    expect($commit->exists)->toBeTrue()
        ->and($commit->body)->toBe('Hello, devnull!')
        ->and($commit->user->is($user))->toBeTrue();
});

it('creates commits pending review', function (): void {
    $action = resolve(CreateCommit::class);

    $commit = $action->handle(User::factory()->create(), 'Initial commit');

    expect($commit->status)->toBe(CommitStatus::Pending);
});

it('stores emoji shortcodes as their emoji', function (): void {
    $action = resolve(CreateCommit::class);

    $commit = $action->handle(User::factory()->create(), 'shipped it :heart :like');

    expect($commit->body)->toBe('shipped it ❤️ 👍');
});

it('approves commits of admins right away', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $action = resolve(CreateCommit::class);

    $commit = $action->handle(User::factory()->create(['username' => 'nunomaduro']), 'Initial commit');

    expect($commit->status)->toBe(CommitStatus::Approved);
});

it('notifies the parent author about replies of admins', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $parent = Commit::factory()->create();

    $action = resolve(CreateCommit::class);

    $reply = $action->handle(User::factory()->create(['username' => 'nunomaduro']), 'Nice one', $parent);

    expect($parent->user->notifications()->sole()->commit_id)->toBe($reply->id);
});

it('does not notify about pending replies', function (): void {
    $parent = Commit::factory()->create();

    $action = resolve(CreateCommit::class);

    $action->handle(User::factory()->create(), 'Nice one', $parent);

    expect(Notification::query()->count())->toBe(0);
});

it('attaches a link preview for the first url in the body', function (): void {
    Queue::fake();

    $action = resolve(CreateCommit::class);

    $commit = $action->handle(User::factory()->create(), "Shipped https://example.com/first.\nAlso https://example.com/second");

    expect($commit->refresh()->linkPreview)
        ->url->toBe('https://example.com/first')
        ->url_hash->toBe(hash('sha256', 'https://example.com/first'))
        ->fetched_at->toBeNull();

    Queue::assertPushed(UnfurlLink::class, fn (UnfurlLink $job): bool => $job->linkPreview->is($commit->linkPreview));
});

it('attaches a link preview for a bare domain', function (): void {
    Queue::fake();

    $action = resolve(CreateCommit::class);

    $commit = $action->handle(User::factory()->create(), 'Shipped nativephp.com');

    expect($commit->refresh()->linkPreview)
        ->url->toBe('https://nativephp.com')
        ->url_hash->toBe(hash('sha256', 'https://nativephp.com'));

    Queue::assertPushed(UnfurlLink::class);
});

it('attaches a link preview to replies', function (): void {
    Queue::fake();

    $parent = Commit::factory()->create();

    $action = resolve(CreateCommit::class);

    $reply = $action->handle(User::factory()->create(), 'Docs are at https://example.com/docs', $parent);

    expect($reply->refresh())
        ->parent_id->toBe($parent->id)
        ->linkPreview->url->toBe('https://example.com/docs');

    Queue::assertPushed(UnfurlLink::class);
});

it('reuses the link preview of a url that was linked before', function (): void {
    Queue::fake();

    $linkPreview = LinkPreview::factory()->create(['url' => 'https://example.com']);

    $action = resolve(CreateCommit::class);

    $first = $action->handle(User::factory()->create(), 'Look at https://example.com');
    $second = $action->handle(User::factory()->create(), 'Also https://example.com!');

    expect(LinkPreview::query()->count())->toBe(1)
        ->and($first->link_preview_id)->toBe($linkPreview->id)
        ->and($second->link_preview_id)->toBe($linkPreview->id);
});

it('only fetches link previews that were never fetched', function (): void {
    Queue::fake();

    LinkPreview::factory()->fetched()->create(['url' => 'https://example.com']);

    $action = resolve(CreateCommit::class);

    $commit = $action->handle(User::factory()->create(), 'Look at https://example.com');

    expect($commit->linkPreview)->not->toBeNull();

    Queue::assertNothingPushed();
});

it('fetches the link preview once the commit is saved', function (): void {
    Queue::fake();

    $action = resolve(CreateCommit::class);

    $commit = $action->handle(User::factory()->create(), 'Look at https://example.com');

    Queue::assertPushed(UnfurlLink::class);

    expect($commit->exists)->toBeTrue();
});

it('attaches no link preview without a url', function (): void {
    Queue::fake();

    $action = resolve(CreateCommit::class);

    $commit = $action->handle(User::factory()->create(), 'Shipped Node.js 24, see README.md');

    expect($commit->refresh()->link_preview_id)->toBeNull()
        ->and(LinkPreview::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});
