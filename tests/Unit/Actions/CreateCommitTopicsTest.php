<?php

declare(strict_types=1);

use App\Actions\CreateCommitTopics;
use App\Models\Commit;
use App\Models\Organization;

it('links a commit to the organizations it mentions, by the handle written', function (): void {
    $laravel = Organization::factory()->create(['handle' => 'laravel']);
    $digits = Organization::factory()->create(['handle' => '1337']);
    $commit = Commit::factory()->create(['body' => '@Laravel and @1337', 'mentions' => []]);

    resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $laravel->id, '1337' => $digits->id]);

    expect($commit->refresh()->organization_mentions)->toHaveCount(2)->toMatchArray(['laravel' => $laravel->id, '1337' => $digits->id]);
});

it('keeps what a reference already links to', function (): void {
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();
    $commit = Commit::factory()->create(['body' => '@laravel', 'mentions' => []]);

    resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $first->id]);
    resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $second->id]);

    expect($commit->refresh()->organization_mentions)->toBe(['laravel' => $first->id]);
});
