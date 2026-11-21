<?php

declare(strict_types=1);

use App\Models\LinkPreview;
use App\Services\CanonicalUrls;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Previews of the same canonical URL become the one fetched last, under that URL, for every commit and message that
     * showed any of them. The others go before it takes their URL's hash.
     */
    public function up(): void
    {
        $canonicalUrls = resolve(CanonicalUrls::class);

        LinkPreview::query()
            ->orderByRaw('fetched_at desc nulls last')
            ->latest()
            ->get(['id', 'url'])
            ->groupBy(fn (LinkPreview $preview): string => $canonicalUrls->canonical($preview->url))
            ->each(function (Collection $previews, string $url): void {
                $survivor = $previews->firstOrFail();
                $duplicates = $previews->skip(1)->modelKeys();

                if ($duplicates === [] && $survivor->url === $url) {
                    return;
                }

                DB::table('commits')->whereIn('link_preview_id', $duplicates)->update(['link_preview_id' => $survivor->id]);
                DB::table('messages')->whereIn('link_preview_id', $duplicates)->update(['link_preview_id' => $survivor->id]);
                LinkPreview::query()->whereKey($duplicates)->delete();
                LinkPreview::query()->whereKey($survivor->id)->update(['url' => $url, 'url_hash' => hash('sha256', $url)]);
            });
    }
};
