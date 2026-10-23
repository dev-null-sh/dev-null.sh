<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CommitVideo;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteCommitVideoRequest extends FormRequest
{
    public function authorize(): Response
    {
        $video = $this->route('commitVideo');
        assert($video instanceof CommitVideo);

        return $video->user_id === $this->user()?->id && $video->commit_id === null ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array{} */
    public function rules(): array
    {
        return [];
    }
}
