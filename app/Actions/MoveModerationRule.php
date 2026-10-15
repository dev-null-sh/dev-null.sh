<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ModerationRule;
use Illuminate\Support\Facades\DB;

final readonly class MoveModerationRule
{
    /**
     * Swaps the rule with its neighbour. Order is only for reading the list: every rule is asked about every commit.
     */
    public function handle(ModerationRule $rule, bool $up): void
    {
        DB::transaction(function () use ($rule, $up): void {
            $rules = ModerationRule::query()->ordered()->lockForUpdate()->get();
            $index = (int) $rules->search(fn (ModerationRule $candidate): bool => $candidate->is($rule));
            $rules = $rules->all();
            $target = $up ? $index - 1 : $index + 1;

            if (! isset($rules[$target])) {
                return;
            }

            [$rules[$index], $rules[$target]] = [$rules[$target], $rules[$index]];

            foreach ($rules as $position => $ordered) {
                $ordered->update(['position' => $position]);
            }
        });
    }
}
