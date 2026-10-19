<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Branch;
use App\Models\Commit;
use App\Models\User;
use App\Services\BranchStyles;

it('summarises branch header fields for commit chrome', function (): void {
    $author = User::factory()->create();
    $other = User::factory()->create();

    Branch::factory()->verified('ruby-lang.org')->create([
        'name' => 'ruby',
        'brand_color' => '#e42527',
        'description' => 'A dynamic language',
        'og_image_url' => 'https://ruby-lang.org/og.png',
    ]);

    Commit::factory()->for($author)->create([
        'branch' => 'ruby',
        'status' => CommitStatus::Approved,
    ]);
    Commit::factory()->for($other)->create([
        'branch' => 'ruby',
        'status' => CommitStatus::Approved,
    ]);
    Commit::factory()->for($author)->create([
        'branch' => 'ruby',
        'status' => CommitStatus::Pending,
    ]);

    resolve(BranchStyles::class)->forget();

    expect(resolve(BranchStyles::class)->summary('ruby'))->toMatchArray([
        'name' => 'ruby',
        'commits_count' => 2,
        'authors_count' => 2,
        'is_verified' => true,
        'brand_color' => '#e42527',
        'verified_domain' => 'ruby-lang.org',
        'description' => 'A dynamic language',
        'og_image_url' => 'https://ruby-lang.org/og.png',
    ]);
});
