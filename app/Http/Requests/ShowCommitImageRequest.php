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
     * Pending and rejected images never leave the server for anyone but their author and moderators, or the temporary
     * URL the API gave one of them, until the commit is deleted.
     */
    public function authorize(): Response
    {
        $commitImage = $this->route('commitImage');
        assert($commitImage instanceof CommitImage);

        $commit = Commit::query()->find($commitImage->commit_id);

        return $commit instanceof Commit && ($commit->imagesAreVisibleTo($this->user()) || (! $commit->trashed() && $this->hasValidSignature())) && Storage::disk()->exists($commitImage->path)
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
