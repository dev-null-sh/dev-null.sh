<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\DeleteCommitContent;
use App\Models\Commit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

#[DeleteWhenMissingModels]
final class EraseDeletedCommit implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Commit $commit)
    {
        //
    }

    public function handle(DeleteCommitContent $action): void
    {
        $action->handle($this->commit);
    }
}
