<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\CanonicalUrls;
use Illuminate\Foundation\Http\FormRequest;

final class ShowApiLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string'],
            'sort' => ['nullable', 'string', 'in:top,latest'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return ['url' => $this->route('url'), 'sort' => $this->query('sort')];
    }

    /**
     * The link as commits store it. A link written another way is answered as that link instead of redirected, since a
     * signed request's signature covers the path.
     */
    public function link(): string
    {
        $url = $this->route('url');
        assert(is_string($url));

        return resolve(CanonicalUrls::class)->canonical('https://'.$url);
    }

    public function isNewestFirst(): bool
    {
        return $this->query('sort') === 'latest';
    }
}
