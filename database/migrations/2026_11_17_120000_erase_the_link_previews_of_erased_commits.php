<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Uri\WhatWg\Url;

return new class extends Migration
{
    /**
     * A card is left behind when the commit its link opens is gone or has no author any more, or replies to one that has
     * none, since a reply's title names who it replies to. What someone posted for an organization keeps the organization
     * as its author once their account is gone, so its cards stay.
     */
    public function up(): void
    {
        $host = (string) Url::parse(config()->string('app.url'))?->getAsciiHost();

        DB::table('link_previews')->select(['id', 'url'])->chunkById(1000, function (Collection $previews) use ($host): void {
            /** @var Collection<int, object{id: string, url: string}> $previews */
            $linked = $previews->mapWithKeys(fn (object $preview): array => [$preview->id => $this->commitId($preview->url, $host)])->filter();
            $kept = DB::table('commits')
                ->whereIn('id', $linked->unique()->values()->all())
                ->whereNotNull('author_id')
                ->where(fn (Builder $query): Builder => $query->whereNull('parent_id')
                    ->orWhereExists(fn (Builder $parent): Builder => $parent->from('commits as parents')
                        ->whereColumn('parents.id', 'commits.parent_id')
                        ->whereNotNull('parents.author_id')))
                ->pluck('id');

            DB::table('link_previews')->whereIn('id', $linked->reject(fn (string $commit): bool => $kept->contains($commit))->keys()->all())->delete();
        });
    }

    /**
     * The commit a link to this site opens, read the way the preview fetcher and the router read it, since escapes, dot
     * segments and backslashes still open it. Frozen here so later changes to either cannot change this cleanup.
     */
    private function commitId(string $url, string $host): string
    {
        $link = Url::parse($url);
        $linkHost = (string) $link?->getAsciiHost();
        $id = Str::match('#^/@[^/]+/commits/([0-9a-f-]{36})#', mb_strtolower(rawurldecode((string) $link?->getPath())));

        return ($linkHost === $host || str_ends_with($linkHost, ".{$host}")) && Str::isUuid($id) ? $id : '';
    }
};
