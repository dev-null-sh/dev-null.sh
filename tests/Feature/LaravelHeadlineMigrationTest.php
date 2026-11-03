<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Headline;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;

it('removes the Laravel topic and resets its commits for backfill without removing specific headlines', function (): void {
    $laravel = Headline::factory()->create(['name' => 'Laravel']);
    $specific = Headline::factory()->create(['name' => 'Laravel AI SDK release']);
    $affected = Commit::factory()->hasAttached($laravel)->create(['headlines_classified_at' => now()]);
    $shared = Commit::factory()->hasAttached($laravel)->hasAttached($specific)->create(['headlines_classified_at' => now()]);
    $unaffected = Commit::factory()->hasAttached($specific)->create(['headlines_classified_at' => now()]);

    $migration = require database_path('migrations/2026_11_03_150501_remove_laravel_headline.php');
    $migration->up();
    $migration->up();

    assertModelMissing($laravel);
    assertModelExists($specific);
    assertDatabaseMissing('commit_headline', ['headline_id' => $laravel->id]);
    assertDatabaseHas('commits', ['id' => $affected->id, 'headlines_classified_at' => null]);
    assertDatabaseHas('commits', ['id' => $shared->id, 'headlines_classified_at' => null]);
    assertDatabaseHas('commits', ['id' => $unaffected->id, 'headlines_classified_at' => $unaffected->headlines_classified_at]);
    assertDatabaseHas('commit_headline', ['commit_id' => $shared->id, 'headline_id' => $specific->id]);
    assertDatabaseHas('commit_headline', ['commit_id' => $unaffected->id, 'headline_id' => $specific->id]);
});
