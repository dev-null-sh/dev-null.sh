<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowUserCoverImageRequest extends FormRequest
{
    /**
     * A cover waiting for a moderator never leaves the server for anyone but its owner and moderators.
     */
    public function authorize(): Response
    {
        $user = $this->route('user');
        assert($user instanceof User);

        $viewer = $this->user();
        assert($viewer === null || $viewer instanceof User);

        return $user->coverImageUrl($viewer) !== null && Storage::disk()->exists((string) $user->cover_image_path)
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
}
