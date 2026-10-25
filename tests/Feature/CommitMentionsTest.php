<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\User;

it('keeps the original mention recipient and profile after a rename and handle reuse', function (): void {
    config(['app.admins' => ['moderator']]);

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['handle' => 'original']);
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $body = 'Thanks @Original and @ORIGINAL!';

    $this->actingAs($author)->post('/commits', [
        'body' => $body,
        'mentions' => ['original' => $moderator->id],
    ])->assertRedirect();

    $commit = $author->commits()->sole();

    expect($commit->mentions)->toBe(['original' => $mentioned->id]);
    expect($mentioned->notifications()->count())->toBe(0);

    $mentioned->profile->update(['handle' => 'renamed']);
    $replacement = User::factory()->create(['handle' => 'original']);

    $this->actingAs($moderator)->post(route('commit-approval.store', $commit))->assertRedirect();

    expect($mentioned->notifications()->sole()->type)->toBe(NotificationType::Mentioned);
    expect($replacement->notifications()->count())->toBe(0);
    expect($commit->refresh()->body)->toBe($body);

    $this->actingAs($author)->get(route('commit.show', [$author, $commit]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commit.mentions.original', $mentioned->id)
            ->where('commit.body', $body));

    $this->get(route('mention.show', $mentioned->id))
        ->assertRedirect(route('user.show', ['user' => $mentioned]));

    $this->get(route('user.show', ['user' => $mentioned]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('user.id', $mentioned->id));
});

it('does not bind an unknown mention to an account created before approval', function (): void {
    config(['app.admins' => ['moderator']]);

    $author = User::factory()->create();
    $moderator = User::factory()->create(['handle' => 'moderator']);

    $this->actingAs($author)->post('/commits', ['body' => 'Thanks @futuredev!'])->assertRedirect();

    $commit = $author->commits()->sole();
    $newcomer = User::factory()->create(['handle' => 'futuredev']);

    $this->actingAs($moderator)->post(route('commit-approval.store', $commit))->assertRedirect();

    expect($commit->refresh()->mentions)->toBe([]);
    expect($newcomer->notifications()->count())->toBe(0);
});

it('only captures mentions in prose', function (): void {
    $author = User::factory()->create();
    $mentioned = User::factory()->create(['handle' => 'original']);
    User::factory()->create(['handle' => 'codeuser']);

    $this->actingAs($author)->post('/commits', [
        'body' => "Thanks @ORIGINAL, `@codeuser`\n```php\n@codeuser\n```\nmail@codeuser.com @codeuser/package",
    ])->assertRedirect();

    expect($author->commits()->sole()->mentions)->toBe(['original' => $mentioned->id]);
});

it('ignores deleted recipients at approval and returns 404 for their mention links', function (): void {
    config(['app.admins' => ['moderator']]);

    $author = User::factory()->create();
    $mentioned = User::factory()->create(['handle' => 'original']);
    $moderator = User::factory()->create(['handle' => 'moderator']);

    $this->actingAs($author)->post('/commits', ['body' => 'Thanks @original!'])->assertRedirect();

    $commit = $author->commits()->sole();
    $id = $mentioned->id;
    resolve(DeleteUser::class)->handle($mentioned);
    $replacement = User::factory()->create(['handle' => 'original']);

    $this->actingAs($moderator)->post(route('commit-approval.store', $commit))->assertRedirect();

    expect($replacement->notifications()->count())->toBe(0);

    $this->get('/mentions/'.$id)->assertNotFound();
});

it('redirects guests to the current profile and rejects invalid mention ids', function (): void {
    $mentioned = User::factory()->create(['handle' => 'original']);

    $this->get('/mentions/'.$mentioned->id)->assertRedirect('/@original');
    $this->get('/mentions/original')->assertNotFound();
});

it('keeps captured mention identities through deletion and undo after a rename and handle reuse', function (?string $relationship): void {
    config(['app.admins' => ['moderator']]);

    $author = User::factory()->create(['handle' => 'moderator']);
    $mentioned = User::factory()->create(['handle' => 'original']);
    User::factory()->create(['handle' => 'codeuser']);
    $body = "Thanks @ORIGINAL! `@codeuser`\n```php\n@codeuser\n```";
    $target = Commit::factory()->create();
    $payload = ['body' => $body];

    if ($relationship !== null) {
        $payload[$relationship] = $target->id;
    }

    $this->actingAs($author)->post(route('commit.store'), $payload)
        ->assertRedirect()->assertSessionHasNoErrors();
    $commit = $author->commits()->sole();
    $segments = $commit->toArray()['body_segments'];

    expect($commit->mentions)->toBe(['original' => $mentioned->id])
        ->and($mentioned->notifications()->where('type', NotificationType::Mentioned)->count())->toBe(1);

    $mentioned->profile->update(['handle' => 'renamed']);
    $replacement = User::factory()->create(['handle' => 'original']);

    $this->delete(route('commit.destroy', $commit))->assertRedirect();

    expect(Commit::withTrashed()->findOrFail($commit->id)->toArray())
        ->not->toHaveKeys(['body', 'body_segments', 'mentions']);

    $this->post(route('commit-restoration.store', $commit))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.mentions.original', $mentioned->id)
        ->assertInertiaFlash('restored.body', $body)
        ->assertInertiaFlash('restored.body_segments', $segments);

    expect($commit->refresh()->mentions)->toBe(['original' => $mentioned->id])
        ->and($mentioned->notifications()->where('type', NotificationType::Mentioned)->count())->toBe(0)
        ->and($replacement->notifications()->count())->toBe(0);

    $this->get(route('commit.show', [$author, $commit]))
        ->assertInertia(fn ($page) => $page
            ->where('commit.mentions.original', $mentioned->id)
            ->where('commit.body_segments', $segments));
    $this->get(route('mention.show', $mentioned->id))
        ->assertRedirect(route('user.show', $mentioned));
})->with([
    'commit' => null,
    'reply' => 'parent_id',
    'quote' => 'quoted_commit_id',
]);
