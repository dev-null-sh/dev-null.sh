<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Queries\SitemapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowCommitSitemapRequest extends FormRequest
{
    /**
     * A day that doesn't exist, like 2026-02-30, or without an approved commit, has no sitemap, so search engines
     * drop a sitemap once everything it listed is gone.
     */
    public function authorize(): Response
    {
        return $this->day()->format('Y-m-d') === $this->route('day') && resolve(SitemapQuery::class)->hasCommitsOn($this->day())
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
