<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Talk;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteTalkRequest extends FormRequest
{
    public function authorize(): bool
    {
        $talk = $this->route('talk');
        assert($talk instanceof Talk);

        return $this->user()?->id === $talk->user_id;
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
