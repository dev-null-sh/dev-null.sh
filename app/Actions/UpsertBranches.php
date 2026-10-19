<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class UpsertBranches
{
    /**
     * Ensures each branch name has a branches row, without granting membership.
     *
     * @param  list<string>  $names
     */
    public function handle(array $names): void
    {
        if ($names === []) {
            return;
        }

        $now = now();

        DB::table('branches')->insertOrIgnore(array_map(
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
     * @return list<Branch>
     */
    public function branches(array $names): array
    {
        $this->handle($names);

        return Branch::query()->whereIn('name', $names)->get()->all();
    }
}
