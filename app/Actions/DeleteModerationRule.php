<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ModerationRule;

final readonly class DeleteModerationRule
{
    /**
     * Past evaluations keep their own snapshot of the rule, so they still read correctly after it's gone.
     */
    public function handle(ModerationRule $rule): void
    {
        $rule->delete();
    }
}
