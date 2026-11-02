<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use Illuminate\Http\UploadedFile;

final readonly class CreateCommitImages
{
    public const int MAX_IMAGES = 3;

    public const int MAX_DESCRIPTION_LENGTH = 1000;

    public const string MAX_UPLOAD_SIZE = '10mb';

    /**
     * Larger sources are refused before they are decoded, so a tiny file cannot expand into gigabytes of pixels.
     */
    public const int MAX_SOURCE_DIMENSION = 6000;

    public function __construct(private CreateImageFiles $createImageFiles)
    {
        //
    }

    /**
     * @param  list<UploadedFile>  $images
     * @param  array<int, string|null>  $descriptions
     */
    public function handle(Commit $commit, array $images, array $descriptions = []): void
    {
        $commit->images()->createMany($this->createImageFiles->handle($images, $descriptions));
    }
}
