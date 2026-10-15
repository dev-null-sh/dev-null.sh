<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ModerationRule;
use Illuminate\Support\Facades\DB;

final readonly class CreateModerationRule
{
    /**
     * New rules go last and start in Test mode unless told otherwise, so they never act before anyone saw them work.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): ModerationRule
    {
        return DB::transaction(function () use ($attributes): ModerationRule {
            $last = ModerationRule::query()->lockForUpdate()->orderByDesc('position')->first();

            return ModerationRule::query()->create([
                ...$attributes,
                'position' => $last === null ? 0 : $last->position + 1,
            ]);
        });
    }
}
