<?php

declare(strict_types=1);

use App\Models\LinkPreview;
use App\Services\CanonicalUrls;
use App\Services\Urls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Merging the previews by canonical URL dropped their fragment, which points to an entry, a line of code or an app's
     * state. Each commit whose card is the first link of its text to the page, written with a fragment, gets it back,
     * under a preview of its own copied from the page's, as a fragment never changes what is fetched. A page still
     * waiting to be fetched is left alone, as nothing would fetch its copy.
     */
    public function up(): void
    {
        $canonicalUrls = resolve(CanonicalUrls::class);
        $urls = resolve(Urls::class);

        /** @var list<object{id: string, body: string, link_preview_id: string}> $commits */
        $commits = DB::table('commits')
            ->join('link_previews', 'link_previews.id', '=', 'commits.link_preview_id')
            ->where('commits.body', 'like', '%#%')
            ->where('link_previews.url', 'not like', '%#%')
            ->whereNotNull('link_previews.fetched_at')
            ->orderBy('commits.id')
            ->get(['commits.id', 'commits.body', 'commits.link_preview_id'])
            ->all();

        foreach ($commits as $commit) {
            $page = LinkPreview::query()->findOrFail($commit->link_preview_id);
            $url = collect($urls->all($commit->body))
                ->map(fn (string $url): string => $canonicalUrls->canonical($url))
                ->first(fn (string $url): bool => Str::before($url, '#') === $page->url);

            if ($url === null || ! str_contains($url, '#')) {
                continue;
            }

            $preview = LinkPreview::query()->where('url_hash', hash('sha256', $url))->first()
                ?? tap($page->replicate(), fn (LinkPreview $copy) => $copy->forceFill(['url' => $url, 'url_hash' => hash('sha256', $url)])->save());

            DB::table('commits')->where('id', $commit->id)->update(['link_preview_id' => $preview->id]);
        }
    }
};
