<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListAppsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Whether the visitor browses from a Mac, so the page can be honest about the download everywhere else. iPads that
     * ask for desktop sites say they are Macs too, which the page corrects once it runs in the browser.
     */
    public function isMac(): bool
    {
        return str_contains((string) $this->userAgent(), 'Macintosh');
    }
}
