<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $names = [];

        DB::table('commits')
            ->whereNotNull('hashtags')
            ->whereRaw("hashtags::text <> '[]'")
            ->select(['id', 'hashtags'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $commits) use (&$names): void {
                foreach ($commits as $commit) {
                    $hashtags = is_string($commit->hashtags)
                        ? json_decode($commit->hashtags, true, flags: JSON_THROW_ON_ERROR)
                        : $commit->hashtags;

                    if (! is_array($hashtags)) {
                        continue;
                    }

                    foreach ($hashtags as $name) {
                        if (is_string($name) && $name !== '') {
                            $names[$name] = true;
                        }
                    }
                }
            });

        $now = now();

        foreach (array_keys($names) as $name) {
            DB::table('branches')->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
