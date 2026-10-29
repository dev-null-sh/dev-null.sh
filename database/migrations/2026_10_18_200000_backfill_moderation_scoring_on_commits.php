<?php

declare(strict_types=1);

use App\Actions\UpdateCommitModeration;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Moderation\DeterministicModerationSignals;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Each chunk commits on its own, so the backlog never holds a transaction (or the commits table) open.
    public $withinTransaction = false;

    public function up(): void
    {
        // Runs after every schema migration, and a fresh database has nothing to score, so it never reaches the model.
        if (! Commit::query()->toBase()->whereIn('status', [CommitStatus::Pending, CommitStatus::Rejected])->exists()) {
            return;
        }

        // Hash first so every pending commit can match the pending and rejected ones, whichever order they are scored in.
        $signals = resolve(DeterministicModerationSignals::class);

        Commit::query()
            ->whereIn('status', [CommitStatus::Pending, CommitStatus::Rejected])
            ->chunkById(500, function ($commits) use ($signals): void {
                foreach ($commits as $commit) {
                    $commit->forceFill(['body_hash' => $signals->bodyHash($commit->body)])->saveQuietly();
                }
            });

        Commit::query()
            ->where('status', CommitStatus::Pending)
            ->chunkById(100, function ($commits): void {
                foreach ($commits as $commit) {
                    resolve(UpdateCommitModeration::class)->handle($commit);
                }
            });
    }
};
