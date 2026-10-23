<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowIssueImageRequest extends FormRequest
{
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
        return 'issue-images/'.$this->route('reporter').'/'.$this->route('image').'.webp';
    }
}
