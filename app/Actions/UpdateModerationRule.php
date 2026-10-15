<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ModerationRule;

final readonly class UpdateModerationRule
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(ModerationRule $rule, array $attributes): ModerationRule
    {
        $rule->update($attributes);

        return $rule;
    }
}
