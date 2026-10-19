<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class UpsertTags
{
    /**
     * Ensures each hashtag name has a tags row, without granting membership.
     *
     * @param  list<string>  $names
     */
    public function handle(array $names): void
    {
        if ($names === []) {
            return;
        }

        $now = now();

        DB::table('tags')->insertOrIgnore(array_map(
            fn (string $name): array => [
                'id' => Str::uuid7()->toString(),
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $names,
        ));
    }

    /**
     * @param  list<string>  $names
     * @return list<Tag>
     */
    public function tags(array $names): array
    {
        $this->handle($names);

        return Tag::query()->whereIn('name', $names)->get()->all();
    }
}
