<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowCommitSitemapRequest extends FormRequest
{
    /**
     * A day that doesn't exist, like 2026-02-30, has no sitemap.
     */
    public function authorize(): Response
    {
        return $this->day()->format('Y-m-d') === $this->route('day') ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The start of the day whose commits the sitemap lists, in UTC.
     */
    public function day(): CarbonImmutable
    {
        $day = $this->route('day');
        assert(is_string($day));

        $start = CarbonImmutable::createFromFormat('!Y-m-d', $day);
        assert($start instanceof CarbonImmutable);

        return $start;
    }
}
