<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitVideoStatus;
use App\Models\CommitVideo;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateCommitVideoUploadRequest extends FormRequest
{
    public function authorize(): Response
    {
        $video = $this->route('commitVideo');
        assert($video instanceof CommitVideo);

        return config('filesystems.disks.'.config()->string('filesystems.default').'.driver') === 'local'
            && $video->user_id === $this->user()?->id && $video->status === CommitVideoStatus::Uploading
            && $video->created_at->greaterThan(now()->subMinutes(15))
            && (int) $this->header('Content-Length') === $video->size
            ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array{} */
    public function rules(): array
    {
        return [];
    }
}
