<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;

it('returns a visible commit with the content needed by its quote card', function (): void {
    $commit = Commit::factory()->create(['body' => "Hello\n```php\nreturn true;\n```"]);
    $image = CommitImage::factory()->for($commit)->create();

    $this->getJson(route('commit-preview.show', [$commit->user, $commit]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('commit.id', $commit->id)
        ->assertJsonPath('commit.user.id', $commit->user_id)
        ->assertJsonPath('commit.body', $commit->body)
        ->assertJsonPath('commit.body_segments.1.type', 'block')
        ->assertJsonPath('commit.images.0.id', $image->id)
        ->assertJsonMissingPath('commit.embedding')
        ->assertJsonMissingPath('commit.quoted_commit');
});

it('does not replace the page a guest returns to after signing in', function (): void {
    $commit = Commit::factory()->create();
    $intended = route('user.show', $commit->user);

    $this->withSession(['url.intended' => $intended])
        ->getJson(route('commit-preview.show', [$commit->user, $commit]))
        ->assertOk()
        ->assertSessionHas('url.intended', $intended);
});

it('does not expose pending or rejected linked commits to other viewers', function (CommitStatus $status, bool $signedIn): void {
    $commit = Commit::factory()->create(['status' => $status]);

    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    $this->getJson(route('commit-preview.show', [$commit->user, $commit]))->assertNotFound();
})->with([CommitStatus::Pending, CommitStatus::Rejected])->with([false, true]);

it('lets the author preview their own unpublished commit', function (CommitStatus $status): void {
    $commit = Commit::factory()->create(['status' => $status]);

    $this->actingAs($commit->user)->getJson(route('commit-preview.show', [$commit->user, $commit]))
        ->assertOk()->assertJsonPath('commit.id', $commit->id);
})->with([CommitStatus::Pending, CommitStatus::Rejected]);

it('hides linked commits across a block in either direction', function (bool $viewerBlocks): void {
    $viewer = User::factory()->create();
    $commit = Commit::factory()->create();
    $viewerBlocks ? $viewer->blocking()->attach($commit->user) : $commit->user->blocking()->attach($viewer);

    $this->actingAs($viewer)->getJson(route('commit-preview.show', [$commit->user, $commit]))->assertNotFound();
})->with([false, true]);

it('does not resolve deleted commits or a commit under the wrong author', function (): void {
    $commit = Commit::factory()->create();

    $this->getJson(route('commit-preview.show', [User::factory()->create(), $commit]))->assertNotFound();
    $commit->delete();
    $this->getJson(route('commit-preview.show', [$commit->user, $commit]))->assertNotFound();
});

it('shows linked poll results only when the viewer may see them', function (): void {
    $viewer = User::factory()->create();
    $poll = Poll::factory()->withOptions('Tabs', 'Spaces')->create();
    $url = route('commit-preview.show', [$poll->commit->user, $poll->commit]);

    $this->getJson($url)->assertOk()->assertJsonPath('commit.poll.total_votes', null);

    PollVote::factory()->for($poll->options->first(), 'option')->for($viewer)->create();

    $this->actingAs($viewer)->getJson($url)->assertOk()
        ->assertJsonPath('commit.poll.total_votes', 1)
        ->assertJsonPath('commit.poll.has_voted', true);
});

it('renders a draft the way the commit it becomes is rendered', function (): void {
    $user = User::factory()->create();
    User::factory()->create(['handle' => 'nunomaduro']);
    $body = "  shipped :rocket with @nunomaduro\n```php\n\$name = 'devnull';\n```\nrun `composer test` :fire  ";

    $preview = $this->actingAs($user)->postJson(route('commit-preview.store'), ['body' => $body]);

    $this->actingAs($user)->post(route('commit.store'), ['body' => $body]);

    $commit = $user->commits()->sole();

    $preview->assertOk()
        ->assertExactJson(['segments' => $commit->toArray()['body_segments'], 'mentions' => $commit->mentions, 'organization_mentions' => []]);
});

it('links the mentions of a draft to the people the commit would link', function (): void {
    $user = User::factory()->create();
    $alice = User::factory()->create(['handle' => 'Alice']);

    $preview = $this->actingAs($user)->postJson(route('commit-preview.store'), [
        'body' => "Pairing with @alice and @nobody-here\n`@alice` in code is not a mention",
    ]);

    $preview->assertOk()
        ->assertJsonPath('mentions', ['alice' => $alice->id]);
});

it('previews drafts without publishing or spending its commit budget', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 11) as $draft) {
        $this->actingAs($user)
            ->postJson(route('commit-preview.store'), ['body' => "Draft {$draft}"])
            ->assertOk();
    }

    expect($user->commits()->count())->toBe(0);

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Shipped it.'])
        ->assertSessionHasNoErrors();
});

it('collapses runs of blank lines in the preview like in the commit', function (): void {
    $user = User::factory()->create();
    $body = "Shipped it.\n\n\n\nFinally.";

    $preview = $this->actingAs($user)->postJson(route('commit-preview.store'), ['body' => $body]);

    $this->actingAs($user)->post(route('commit.store'), ['body' => $body]);

    $preview->assertOk()
        ->assertJsonPath('segments', $user->commits()->sole()->toArray()['body_segments']);
});

it('requires a body the composer could commit', function (array $data, string $error): void {
    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('commit-preview.store'), $data);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([$error]);
})->with([
    'missing' => [[], 'body'],
    'too long' => [['body' => str_repeat('a', 4001)], 'body'],
]);

it('is not available to guests', function (): void {
    $response = $this->postJson(route('commit-preview.store'), ['body' => 'Hello']);

    $response->assertUnauthorized();
});
