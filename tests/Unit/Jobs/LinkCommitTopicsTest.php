<?php

declare(strict_types=1);

use App\Jobs\LinkCommitTopics;
use App\Models\Commit;
use App\Models\Profile;
use Illuminate\Support\Facades\Http;

it('links the organizations the commit mentions', function (): void {
    Http::fake();

    $organization = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $commit = Commit::factory()->create(['body' => 'Thanks @laravel', 'mentions' => []]);

    dispatch_sync(new LinkCommitTopics($commit));

    expect($commit->refresh()->organization_mentions)->toBe(['laravel' => $organization->id]);
});
