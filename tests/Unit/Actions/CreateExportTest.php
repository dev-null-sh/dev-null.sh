<?php

declare(strict_types=1);

use App\Actions\CreateExport;
use App\Models\Commit;
use App\Models\Topic;
use App\Models\User;

it('excludes operational topic data from the authors export', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    $topic = Topic::factory()->create(['value' => 'Laravel']);
    $commit->topics()->attach($topic);
    Commit::factory()->hasAttached(Topic::factory()->state(['value' => 'Unrelated']))->create();

    $export = iterator_to_array(resolve(CreateExport::class)->handle($user)['commits']);

    expect($export)->toHaveCount(1)
        ->and($export[0]['id'])->toBe($commit->id)
        ->and($export[0])->not->toHaveKeys(['topics', 'topic_decision'])
        ->and(json_encode($export))->not->toContain($topic->id, $topic->value, 'last_applied_at', 'Unrelated');
});

it('exports unclassified commits without topic fields', function (): void {
    $commit = Commit::factory()->create();

    $export = iterator_to_array(resolve(CreateExport::class)->handle($commit->user)['commits']);

    expect($export[0])->not->toHaveKeys(['topics', 'topic_decision']);
});
