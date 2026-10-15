<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\SeriesPosition;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class CreateCommitSeries
{
    public const int MAX_PARTS = 10;

    public function __construct(private CreateCommit $createCommit)
    {
        //
    }

    /**
     * Each part is a reply to the part before it, so the series reads as one thread.
     *
     * @param  list<string>  $bodies
     * @return Collection<int, Commit>
     */
    public function handle(User $user, array $bodies): Collection
    {
        $size = count($bodies);

        throw_if($size < 2 || $size > self::MAX_PARTS, InvalidArgumentException::class, 'A series has between 2 and '.self::MAX_PARTS.' parts.');

        return DB::transaction(function () use ($user, $bodies, $size): Collection {
            $seriesId = Str::uuid7()->toString();
            $parts = new Collection;

            foreach ($bodies as $index => $body) {
                $parts->push($this->createCommit->handle(
                    $user,
                    $body,
                    $parts->last(),
                    new SeriesPosition($seriesId, $index + 1, $size),
                ));
            }

            return $parts;
        });
    }
}
