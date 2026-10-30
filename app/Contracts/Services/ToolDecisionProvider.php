<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;

/**
 * Decides which of Tom's tools the latest message to him needs, so he is made to call them before he answers.
 */
interface ToolDecisionProvider
{
    /**
     * @param  iterable<int, Message>  $history
     * @return Collection<int, Tool>
     */
    public function tools(string $message, iterable $history): Collection;
}
