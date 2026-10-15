<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ModerationRule;
use Illuminate\Support\Facades\DB;

final readonly class UpdateModerationRulePosition
{
    /**
     * Moves the rule to the given place in the list, and numbers the rest around it. Order is only for reading the
     * list: every rule is asked about every commit.
     */
    public function handle(ModerationRule $rule, int $position): void
    {
        DB::transaction(function () use ($rule, $position): void {
            $others = ModerationRule::query()
                ->ordered()
                ->lockForUpdate()
                ->get()
                ->reject(fn (ModerationRule $other): bool => $other->is($rule))
                ->values()
                ->all();

            array_splice($others, min($position, count($others)), 0, [$rule]);

            foreach ($others as $index => $ordered) {
                $ordered->update(['position' => $index]);
            }
        });
    }
}
