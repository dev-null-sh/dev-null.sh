<?php

declare(strict_types=1);

use App\Models\User;

it('renders a draft the way the commit it becomes is rendered', function (): void {
    $user = User::factory()->create();
    User::factory()->create(['username' => 'nunomaduro']);
    $body = "  shipped :rocket with @nunomaduro\n```php\n\$name = 'devnull';\n```\nrun `composer test` :fire  ";

    $preview = $this->actingAs($user)->postJson(route('commit-preview.store'), ['body' => $body]);

    $this->actingAs($user)->post(route('commit.store'), ['body' => $body]);

    $commit = $user->commits()->sole();

    $preview->assertOk()
        ->assertExactJson(['segments' => $commit->toArray()['body_segments'], 'mentions' => $commit->mentions]);
});

it('links the mentions of a draft to the people the commit would link', function (): void {
    $user = User::factory()->create();
    $alice = User::factory()->create(['username' => 'Alice']);

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
