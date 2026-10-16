<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\SeriesPosition;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateCommitSeries
{
    public const int MAX_PARTS = 10;

    public function __construct(private CreateCommit $createCommit)
    {
        //
    }

    /**
     * Each part is a reply to the part before it, so the series reads as one thread. Images go with the first part.
     *
     * @param  list<string>  $bodies
     * @param  list<UploadedFile>  $images
     * @param  array<int, bool|string>  $linkPreviews
     * @return Collection<int, Commit>
     */
    public function handle(User $user, array $bodies, array $images = [], array $linkPreviews = []): Collection
    {
        $size = count($bodies);

        return DB::transaction(function () use ($user, $bodies, $images, $size, $linkPreviews): Collection {
            $seriesId = Str::uuid7()->toString();
            $parts = new Collection;

            foreach ($bodies as $index => $body) {
                $parts->push($this->createCommit->handle(
                    $user,
                    $body,
                    $parts->last(),
                    $index === 0 ? $images : [],
                    linkPreview: $linkPreviews[$index] ?? true,
                    series: new SeriesPosition($seriesId, $index + 1, $size),
                ));
            }

            return $parts;
        });
    }
}
