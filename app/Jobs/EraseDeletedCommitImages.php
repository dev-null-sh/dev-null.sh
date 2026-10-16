<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\DeleteCommitImages;
use App\Models\Commit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

#[DeleteWhenMissingModels]
final class EraseDeletedCommitImages implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Commit $commit)
    {
        //
    }

    public function handle(DeleteCommitImages $action): void
    {
        $action->handle($this->commit);
    }
}
