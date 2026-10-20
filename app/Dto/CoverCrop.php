<?php

declare(strict_types=1);

namespace App\Dto;

use Illuminate\Contracts\Image\Transformation;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Scales an image to cover the given size and crops it at offsets from 0 (left or top) to 1 (right or bottom), the way CSS
 * `object-position` places it. It runs on the decoded image, so the source is read and decoded only once.
 */
final readonly class CoverCrop implements Transformation
{
    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    public function __construct(
        public int $width,
        public int $height,
        public float $offsetX,
        public float $offsetY,
    ) {
        //
    }

    public static function apply(ImageInterface $image, self $crop): ImageInterface
    {
        $scale = max($crop->width / $image->width(), $crop->height / $image->height());

        $image->resize((int) ceil($image->width() * $scale), (int) ceil($image->height() * $scale));

        return $image->crop(
            $crop->width,
            $crop->height,
            (int) round(($image->width() - $crop->width) * $crop->offsetX),
            (int) round(($image->height() - $crop->height) * $crop->offsetY),
        );
    }
}
