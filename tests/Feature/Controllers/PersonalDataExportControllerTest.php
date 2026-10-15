<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('requires authentication to download personal data', function (): void {
    $this->get(route('personal-data-export.show'))->assertRedirectToRoute('home');
});

it('downloads a versioned private JSON file with an explicit profile and empty lists', function (): void {
    $user = User::factory()->create([
        'name' => 'Zoë Example',
        'bio' => "Building tools.\nKeeping them small.",
        'email' => 'zoe@example.test',
        'company' => null,
        'preferred_lane' => 3,
        'remember_token' => 'never-export-this-token',
    ]);
    $original = $user->fresh()->getAttributes();

    $response = $this->actingAs($user)->get(route('personal-data-export.show'));

    $response->assertOk()
        ->assertStreamed()
        ->assertDownload('devnull-export-'.now()->format('Y-m-d-His').'.json')
        ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Accel-Buffering', 'no');
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store');

    $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($data)->toBe([
        'schema_version' => 1,
        'exported_at' => now()->toIso8601String(),
        'profile' => [
            'username' => $user->username,
            'name' => 'Zoë Example',
            'email' => 'zoe@example.test',
            'avatar_url' => $user->avatar_url,
            'bio' => "Building tools.\nKeeping them small.",
            'location' => $user->location,
            'website' => $user->website,
            'company' => null,
            'preferred_lane' => 3,
            'created_at' => $user->created_at->toIso8601String(),
            'updated_at' => $user->updated_at->toIso8601String(),
        ],
        'commits' => [],
        'following' => [],
    ]);
    expect($user->fresh()->getAttributes())->toBe($original);
});

it('exports only the signed-in users live commits including replies and all moderation statuses', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $parent = Commit::factory()->for($other)->rejected()->create(['body' => 'Another developer private parent body']);
    $approved = Commit::factory()->for($user)->create(['body' => "Shipped Unicode support 🚀\nNext: docs."]);
    $pending = Commit::factory()->for($user)->pending()->create();
    $rejected = Commit::factory()->for($user)->rejected()->create();
    $reply = Commit::factory()->for($user)->create(['parent_id' => $parent->id]);
    $deleted = Commit::factory()->for($user)->create(['body' => 'Deleted content must never be exported']);
    $deletedReply = Commit::factory()->for($user)->create(['parent_id' => $approved->id]);
    $survivingReply = Commit::factory()->for($user)->pending()->create(['parent_id' => $deleted->id]);
    $deleted->delete();
    $deletedReply->delete();
    Commit::factory()->for($other)->create(['parent_id' => $approved->id]);
    Commit::factory()->for($other)->pending()->create();
    Commit::factory()->for($other)->rejected()->create();

    $response = $this->actingAs($user)->get(route('personal-data-export.show', ['user_id' => $other->id]));
    $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
    $commits = collect($data['commits'])->keyBy('id');

    expect($commits->keys()->sort()->values()->all())->toBe(collect([
        $approved->id, $pending->id, $rejected->id, $reply->id, $survivingReply->id,
    ])->sort()->values()->all());
    expect($commits[$approved->id])->toBe([
        'id' => $approved->id,
        'parent_id' => null,
        'body' => $approved->body,
        'status' => 'approved',
        'created_at' => $approved->created_at->toIso8601String(),
        'updated_at' => $approved->updated_at->toIso8601String(),
    ])
        ->and($commits[$pending->id]['status'])->toBe('pending')
        ->and($commits[$rejected->id]['status'])->toBe('rejected')
        ->and($commits[$reply->id]['parent_id'])->toBe($parent->id)
        ->and($commits[$survivingReply->id]['parent_id'])->toBe($deleted->id)
        ->and($response->streamedContent())->not->toContain($deleted->body, $deletedReply->body, $parent->body);
});

it('exports following usernames without private identity fields or followers', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create(['username' => 'demo-developer', 'email' => 'private@example.test', 'remember_token' => 'secret-token']);
    $follower = User::factory()->create();
    $user->following()->attach($followed);
    $user->followers()->attach($follower);
    $follower->following()->attach(User::factory()->create());

    $response = $this->actingAs($user)->get(route('personal-data-export.show'));
    $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($data['following'])->toBe([['username' => 'demo-developer']])
        ->and($response->streamedContent())->not->toContain($followed->email, $followed->github_id, $followed->id, $followed->remember_token, $follower->username);
});

it('exports all records across chunk boundaries without omissions or duplicates', function (): void {
    $user = User::factory()->create();
    $commits = Commit::factory()->for($user)->count(501)->create();
    $following = User::factory()->count(501)->create();
    $user->following()->attach($following->modelKeys());

    $response = $this->actingAs($user)->get(route('personal-data-export.show'));
    $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

    expect(collect($data['commits'])->pluck('id')->sort()->values()->all())->toBe($commits->pluck('id')->sort()->values()->all())
        ->and(collect($data['following'])->pluck('username')->sort()->values()->all())->toBe($following->pluck('username')->sort()->values()->all());
});
