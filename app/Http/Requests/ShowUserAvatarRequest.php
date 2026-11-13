<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowUserAvatarRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            return Response::denyAsNotFound();
        }

        $path = $user->avatar_upload_path;
        $version = $this->query('v');

        return is_string($path)
            && is_string($version)
            && hash_equals(hash('xxh3', $path), $version)
            && Storage::disk()->exists($path)
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
