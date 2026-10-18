<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\Talk;
use Illuminate\Support\Facades\DB;

final readonly class DeleteTalk
{
    public function __construct(private DeleteCommit $deleteCommit)
    {
        //
    }

    public function handle(Talk $talk): void
    {
        DB::transaction(function () use ($talk): void {
            $talk = Talk::query()->lockForUpdate()->findOrFail($talk->id);
            $commit = $talk->commit;

            $talk->delete();

            if ($commit instanceof Commit && ! $commit->trashed()) {
                $this->deleteCommit->handle($commit);
            }
        }, attempts: 3);
    }
}
