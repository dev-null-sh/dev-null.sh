<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\Organization;
use App\Models\User;
use App\Services\PageMetadata;

it('omits public metadata for authorless account deletion tombstones', function (CommitStatus $status): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    $reply = Commit::factory()->for($user)->for($root, 'parent')->create(['status' => $status]);
    Commit::factory()->for($reply, 'parent')->create();
    resolve(DeleteUser::class)->handle($user);
    $tombstone = Commit::withTrashed()->findOrFail($reply->id)->loadPublicPreview()->loadCount('stars');

    $metadata = resolve(PageMetadata::class)->commit($tombstone);

    expect($metadata)->toBeNull();
})->with([
    'approved' => [CommitStatus::Approved],
    'pending' => [CommitStatus::Pending],
    'rejected' => [CommitStatus::Rejected],
]);

it('omits public metadata for commits that are not public', function (Closure $state): void {
    $commit = Commit::factory()->create();
    $state($commit);
    $commit->loadPublicPreview()->loadCount('stars');

    $metadata = resolve(PageMetadata::class)->commit($commit);

    expect($metadata)->toBeNull();
})->with([
    'pending' => [fn (Commit $commit): bool => $commit->update(['status' => CommitStatus::Pending])],
    'rejected' => [fn (Commit $commit): bool => $commit->update(['status' => CommitStatus::Rejected])],
    'deleted with author' => [fn (Commit $commit): ?bool => $commit->delete()],
    'authorless' => [fn (Commit $commit): bool => $commit->update(['user_id' => null])],
]);

it('omits an authorless parent from the metadata of a surviving reply', function (): void {
    $user = User::factory()->create();
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($user)->for($root, 'parent')->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    resolve(DeleteUser::class)->handle($user);
    $reply->loadPublicPreview()->loadCount('stars');
    $reply->setRelation('parent', Commit::withTrashed()->findOrFail($parent->id));

    $metadata = resolve(PageMetadata::class)->commit($reply);

    expect($metadata['title'])->toStartWith($reply->user->nameWithHandle().' on /dev/null: “');
    expect($metadata)->toMatchArray([
        'url' => route('commit.show', [$reply->user, $reply]),
        'author' => route('user.show', $reply->user),
    ]);
    expect($metadata['structuredData'])->not->toHaveKey('isPartOf');
});

it('omits public metadata for unsaved or partial commits', function (Closure $factory): void {
    $commit = $factory()->loadPublicPreview();

    expect(resolve(PageMetadata::class)->commit($commit))->toBeNull()
        ->and($commit->replies_count)->toBe(0)
        ->and($commit->isDirty('replies_count'))->toBeFalse();
})->with([
    'unsaved' => fn (): Commit => Commit::factory()->make(['user_id' => null]),
    'partial' => fn (): Commit => Commit::query()->findOrFail(Commit::factory()->pending()->create()->id, ['id', 'user_id', 'parent_id', 'status', 'deleted_at']),
]);

it('describes an organization from its GitHub profile', function (): void {
    $organization = Organization::factory()->create([
        'handle' => 'laravel',
        'name' => 'Laravel',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/958072',
        'bio' => 'The PHP framework for web artisans.',
        'website' => 'https://laravel.com',
    ]);

    $metadata = resolve(PageMetadata::class)->organization($organization);

    expect($metadata)->toMatchArray([
        'type' => 'profile',
        'title' => 'Laravel (@laravel) on /dev/null',
        'description' => 'The PHP framework for web artisans.',
        'url' => route('user.show', 'laravel'),
        'handle' => 'laravel',
    ])
        ->and($metadata['images'][0]['url'])->toBe(route('user-open-graph-image.show', ['profile' => 'laravel', 'v' => $organization->openGraphImageVersion()]))
        ->and($metadata['structuredData']['mainEntity'])->toMatchArray([
            '@type' => 'Organization',
            'name' => 'Laravel',
            'alternateName' => '@laravel',
            'description' => 'The PHP framework for web artisans.',
            'image' => 'https://avatars.githubusercontent.com/u/958072',
            'sameAs' => ['https://github.com/laravel', 'https://laravel.com'],
        ]);
});

it('describes an organization without a name, description, avatar, or website of its own', function (): void {
    $organization = Organization::factory()->create([
        'handle' => 'laravel',
        'name' => 'laravel',
        'avatar_url' => null,
        'bio' => null,
        'website' => null,
    ]);

    $metadata = resolve(PageMetadata::class)->organization($organization);

    expect($metadata['title'])->toBe('@laravel on /dev/null')
        ->and($metadata['description'])->toBe('Read the commits that mention @laravel on /dev/null, a social network for developers.')
        ->and($metadata['structuredData']['mainEntity'])->not->toHaveKeys(['description', 'image'])
        ->and($metadata['structuredData']['mainEntity']['sameAs'])->toBe(['https://github.com/laravel']);
});
