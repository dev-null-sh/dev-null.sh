<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\SeriesPosition;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\Image as ProcessedImage;
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
     * @param  array<int, UploadedFile>  $images
     * @param  array<int, string|null>  $imageDescriptions
     * @param  array<int, bool|string>  $linkPreviews
     * @param  array<int, array{file: UploadedFile, width: int, height: int, poster: ProcessedImage}>  $animations
     * @return Collection<int, Commit>
     */
    public function handle(User $user, array $bodies, array $images = [], array $imageDescriptions = [], array $linkPreviews = [], array $animations = []): Collection
    {
        $size = count($bodies);

        return DB::transaction(function () use ($user, $bodies, $images, $imageDescriptions, $size, $linkPreviews, $animations): Collection {
            $seriesId = Str::uuid7()->toString();
            $parts = new Collection;

            foreach ($bodies as $index => $body) {
                $parts->push($this->createCommit->handle(
                    $user,
                    $body,
                    $parts->last(),
                    images: $index === 0 ? $images : [],
                    imageDescriptions: $index === 0 ? $imageDescriptions : [],
                    animations: $index === 0 ? $animations : [],
                    linkPreview: $linkPreviews[$index] ?? true,
                    series: new SeriesPosition($seriesId, $index + 1, $size),
                ));
            }

            return $parts;
        });
    }
}
