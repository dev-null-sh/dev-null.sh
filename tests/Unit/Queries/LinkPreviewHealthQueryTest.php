<?php

declare(strict_types=1);

use App\Models\LinkPreview;
use App\Queries\LinkPreviewHealthQuery;

it('counts pending link previews and the ones that failed in the last day', function (): void {
    LinkPreview::factory()->count(2)->create();
    LinkPreview::factory()->failed()->count(3)->create();
    LinkPreview::factory()->failed()->create(['fetched_at' => now()->subDays(2)]);
    LinkPreview::factory()->fetched()->create();
    LinkPreview::factory()->withoutTitle()->create();

    expect(resolve(LinkPreviewHealthQuery::class)->get())
        ->toBe(['pending' => 2, 'failed' => 3]);
});
