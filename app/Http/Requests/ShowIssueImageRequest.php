<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowIssueImageRequest extends FormRequest
{
    /**
     * Images of issues are public, so only one that is gone is refused.
     */
    public function authorize(): Response
    {
        return Storage::disk()->exists($this->path()) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    public function path(): string
    {
        $image = $this->route('image');
        assert(is_string($image));

        return 'issue-images/'.$image.'.webp';
    }
}
