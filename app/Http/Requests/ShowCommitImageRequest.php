<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\CommitImage;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowCommitImageRequest extends FormRequest
{
    /**
     * Pending and rejected images never leave the server for anyone but their author and moderators.
     */
    public function authorize(): Response
    {
        $commitImage = $this->commitImage();
        $commit = Commit::query()->find($commitImage->commit_id);

        return $commit instanceof Commit && $commit->imagesAreVisibleTo($this->user()) && Storage::disk()->exists($this->mediaPath())
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['poster' => ['sometimes', 'boolean']];
    }

    public function isPoster(): bool
    {
        return $this->boolean('poster') && $this->commitImage()->isAnimation();
    }

    public function mediaPath(): string
    {
        $image = $this->commitImage();

        return $this->isPoster() ? $image->previewPath() : $image->path;
    }

    private function commitImage(): CommitImage
    {
        $image = $this->route('commitImage');
        assert($image instanceof CommitImage);

        return $image;
    }
}
