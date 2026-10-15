<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Topic;

test('to array', function (): void {
    $topic = Topic::factory()->create()->refresh();

    expect(array_keys($topic->toArray()))
        ->toBe([
            'id',
            'name',
            'created_at',
            'updated_at',
        ]);
});

it('belongs to many commits', function (): void {
    $commit = Commit::factory()->create();
    $topic = Topic::factory()->hasAttached($commit)->create();

    expect($topic->commits->sole()->is($commit))->toBeTrue();
});
