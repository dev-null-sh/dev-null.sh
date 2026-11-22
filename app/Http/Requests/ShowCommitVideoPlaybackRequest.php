<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CommitVideoStatus;
use App\Models\CommitVideo;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowCommitVideoPlaybackRequest extends FormRequest
{
    public function authorize(): Response
    {
        $video = $this->route('commitVideo');
        assert($video instanceof CommitVideo);
        $user = $this->user();
        $visible = $video->commit_id === null
            ? $video->user_id === $user?->id
            : $video->commit !== null && ($video->commit->isVisibleTo($user) || $user?->isAdmin() || $this->hasValidSignature());

        return $video->status === CommitVideoStatus::Ready && $visible ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array{} */
    public function rules(): array
    {
        return [];
    }
}
