<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\CoverCrop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\ImageException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class UpdateUserCoverImage
{
    public const int WIDTH = 1500;

    public const int HEIGHT = 500;

    public const string MAX_UPLOAD_SIZE = '5mb';

    private const int QUALITY = 70;

    /**
     * The upload is cropped to a 3:1 banner at the offsets the author dragged it to and re-encoded as WebP with GD, so
     * metadata such as GPS location is dropped. Covers skip the moderation queue whenever the author's commits would.
     */
    public function handle(User $user, UploadedFile $file, float $offsetX = 0.5, float $offsetY = 0.5): void
    {
        $image = Image::fromUpload($file)
            ->usingGd()
            ->orient()
            ->transform(new CoverCrop(self::WIDTH, self::HEIGHT, $offsetX, $offsetY))
            ->optimize('webp', self::QUALITY);

        try {
            $image->toBytes();
        } catch (ImageException) {
            throw ValidationException::withMessages([
                'cover_image' => 'The cover image must be a JPEG, PNG, or WebP file.',
            ]);
        }

        $isApproved = $user->skipsModeration();

        $previousPath = DB::transaction(function () use ($user, $image, $isApproved): ?string {
            $path = $image->storeAs('cover-images', Str::uuid7()->toString().'.webp');
            assert(is_string($path));

            DB::afterRollBack(fn (): bool => Storage::disk()->delete($path));

            $previousPath = User::query()->lockForUpdate()->findOrFail($user->id)->cover_image_path;

            User::withoutTimestamps(fn (): int => User::query()->whereKey($user->id)->update([
                'cover_image_path' => $path,
                'cover_image_approved_at' => $isApproved ? now() : null,
            ]));

            return $previousPath;
        });

        if ($previousPath !== null) {
            Storage::disk()->delete($previousPath);
        }
    }
}
