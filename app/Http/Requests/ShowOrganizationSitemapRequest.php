<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Queries\SitemapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowOrganizationSitemapRequest extends FormRequest
{
    /**
     * A month that doesn't exist, like 2026-13, or without an organization to list, has no sitemap, so search
     * engines drop a sitemap once everything it listed is gone.
     */
    public function authorize(): Response
    {
        return $this->month()->format('Y-m') === $this->route('month') && resolve(SitemapQuery::class)->hasOrganizationsIn($this->month())
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

    /**
     * The start of the month whose new organizations the sitemap lists, in UTC.
     */
    public function month(): CarbonImmutable
    {
        $month = $this->route('month');
        assert(is_string($month));

        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        assert($start instanceof CarbonImmutable);

        return $start;
    }
}
