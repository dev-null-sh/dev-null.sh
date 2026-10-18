<?php

declare(strict_types=1);

use App\Services\Urls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Items scheduled before the link card could be chosen keep the card of their first link.
     */
    public function up(): void
    {
        Schema::table('scheduled_commits', function (Blueprint $table): void {
            $table->text('link_preview_url')->nullable();
        });

        $urls = resolve(Urls::class);

        DB::table('scheduled_commits')
            ->whereNull('submitted_at')
            ->whereNull('canceled_at')
            ->select(['id', 'body'])
            ->chunkById(500, function (Collection $scheduledCommits) use ($urls): void {
                foreach ($scheduledCommits as $scheduled) {
                    if (! is_string($scheduled->id) || ! is_string($scheduled->body)) {
                        continue;
                    }

                    DB::table('scheduled_commits')->where('id', $scheduled->id)->update(['link_preview_url' => $urls->first($scheduled->body)]);
                }
            });
    }
};
