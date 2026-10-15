<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ActivityGraphTheme;
use Illuminate\Foundation\Http\FormRequest;

final class ShowUserActivityGraphRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The graph is embedded where nobody reads validation errors, so nothing is rejected and anything but a known theme is light.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function theme(): ActivityGraphTheme
    {
        $theme = $this->query('theme');

        return (is_string($theme) ? ActivityGraphTheme::tryFrom($theme) : null) ?? ActivityGraphTheme::Light;
    }
}
