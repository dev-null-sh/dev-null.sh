<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\CanonicalUrls;
use Illuminate\Foundation\Http\FormRequest;

final class ShowLinkRequest extends FormRequest
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
     * The link as its page's URL writes it: every link page is an https one.
     */
    public function requestedLink(): string
    {
        $url = $this->route('url');
        assert(is_string($url));

        return 'https://'.$url;
    }

    /**
     * The link as commits store it, which the page redirects to when the URL writes it another way.
     */
    public function link(): string
    {
        return resolve(CanonicalUrls::class)->canonical($this->requestedLink());
    }

    public function isNewestFirst(): bool
    {
        return $this->query('sort') === 'latest';
    }
}
