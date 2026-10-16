<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Commit;
use App\Models\CommitImage;
use Illuminate\Auth\Access\Response;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowCommitImageRequest extends FormRequest
{
    /**
     * Pending and rejected images never leave the server for anyone but their author and moderators, or the temporary
     * URL the API gave one of them, until the commit is deleted.
     * Cloud storage answers a missing file with its own not found, so it is only asked about local files, saving a round trip on every view.
     */
    public function authorize(): Response
    {
        $commitImage = $this->route('commitImage');
        assert($commitImage instanceof CommitImage);

        $commit = Commit::query()->find($commitImage->commit_id);

        $disk = Storage::disk();

        return $commit instanceof Commit && ($commit->imagesAreVisibleTo($this->user()) || (! $commit->trashed() && $this->hasValidSignature())) && ($disk instanceof AwsS3V3Adapter || $disk->exists($commitImage->path))
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    public function download(): bool
    {
        return $this->boolean('download');
    }
}
