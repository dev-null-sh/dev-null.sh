<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ModerationRuleMode;
use App\Models\ModerationRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<ModerationRule>
 */
final class ModerationRuleBuilder extends Builder
{
    /**
     * Rules in Test mode are evaluated too, so their results can be compared with what moderators decide.
     */
    public function active(): self
    {
        return $this->whereIn('mode', [ModerationRuleMode::Test, ModerationRuleMode::Live]);
    }

    public function ordered(): self
    {
        return $this->orderBy('position')->orderBy('id');
    }
}
