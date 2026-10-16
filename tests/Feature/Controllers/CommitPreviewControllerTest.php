<?php

declare(strict_types=1);

use App\Models\User;

it('renders a draft the way the commit it becomes is rendered', function (): void {
    $user = User::factory()->create();
    $body = "  shipped :rocket with @nunomaduro\n```php\n\$name = 'devnull';\n```\nrun `composer test` :fire  ";

    $preview = $this->actingAs($user)->postJson(route('commit-preview.store'), ['body' => $body]);

    $this->actingAs($user)->post(route('commit.store'), ['body' => $body]);

    $preview->assertOk()
        ->assertExactJson(['segments' => $user->commits()->sole()->toArray()['body_segments']]);
});

it('previews every series part without publishing or spending its commit budget', function (): void {
    $user = User::factory()->create();
    $bodies = array_map(fn (int $part): string => "Part {$part} :rocket with @nunomaduro\n```php\necho {$part};\n```", range(1, 10));
    $previews = [];

    foreach ($bodies as $body) {
        $previews[] = $this->actingAs($user)
            ->postJson(route('commit-preview.store'), ['body' => $body])
            ->assertOk()
            ->json('segments');
    }

    expect($user->commits()->count())->toBe(0);

    $this->actingAs($user)->post(route('commit-series.store'), ['bodies' => $bodies])
        ->assertSessionHasNoErrors();

    $parts = $user->commits()->oldest('series_position')->get();

    expect($parts)->toHaveCount(10);

    foreach ($parts as $index => $part) {
        expect($part->toArray()['body_segments'])->toBe($previews[$index]);
    }
});

it('requires a body the composer could commit', function (array $data, string $error): void {
    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('commit-preview.store'), $data);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([$error]);
})->with([
    'missing' => [[], 'body'],
    'too long' => [['body' => str_repeat('a', 281)], 'body'],
]);

it('is not available to guests', function (): void {
    $response = $this->postJson(route('commit-preview.store'), ['body' => 'Hello']);

    $response->assertUnauthorized();
});
