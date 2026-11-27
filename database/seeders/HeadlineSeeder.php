<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\UpdateCommitEmbedding;
use App\Models\Commit;
use App\Models\Headline;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class HeadlineSeeder extends Seeder
{
    public function __construct(private readonly UpdateCommitEmbedding $updateCommitEmbedding)
    {
        //
    }

    public function run(): void
    {
        DB::transaction(function (): void {
            $users = collect([0, 1])->map(fn (int $index): User => User::query()->firstWhere('github_id', "headline-seed-{$index}")
                ?? User::factory()->create(['github_id' => "headline-seed-{$index}"]));
            $topics = [
                ['Database indexing', 'Developers compare composite and partial indexes to speed up queries without adding unnecessary write overhead.', [
                    'A composite index on tenant_id and created_at took our activity query from 240 ms to 12 ms. The query plan finally stopped scanning the whole table.',
                    'Trying partial indexes for queued jobs in PostgreSQL. Indexing only the pending rows keeps the index small even as completed jobs pile up.',
                ]],
                ['Accessible developer tools', 'Keyboard navigation and screen-reader support are getting attention in editors, terminals and other developer tools.', [
                    'Shipped keyboard navigation for our command palette. Every action now works without a mouse, including nested menus and search results.',
                    'Testing our terminal UI with a screen reader found three unlabeled buttons. Accessibility checks are going into the release checklist.',
                ]],
                ['Laravel community updates and upcoming releases', 'The community shares upgrade notes and practical examples of queues, typed configuration and concurrency.', [
                    'Our Laravel upgrade is ready. Typed configuration caught a missing setting before deploy, and the full test suite stayed green.',
                    'Using Laravel concurrency for independent imports cut our dashboard refresh time in half. Writing up the trade-offs for the next meetup.',
                ]],
                ['Open-source maintenance', null, [
                    'Opened a good first issue for improving the installation guide. Small documentation fixes are a great way to start contributing.',
                    'Set aside Friday afternoon for reviewing community pull requests. Keeping the review queue moving matters as much as shipping new features.',
                ]],
                ['Browser performance', 'Teams explore ways to avoid layout shifts, reduce JavaScript work and keep scrolling smooth on slower devices.', [
                    'Reserved the image dimensions in our feed and removed a layout shift on first load. The fix was two attributes, not another JavaScript observer.',
                    'Profiling our scrolling list on a slower laptop exposed expensive work in every row. Moving that work out of render made the feed noticeably smoother.',
                ]],
            ];

            foreach ($topics as [$name, $summary, $bodies]) {
                $headline = Headline::query()->updateOrCreate(['name' => $name], [
                    'summary' => $summary,
                    'summary_created_at' => $summary === null ? null : now(),
                ]);

                foreach ($users as $index => $user) {
                    $attributes = [
                        'body' => $bodies[$index],
                        'created_at' => now()->subMinutes(15 + $index * 30),
                    ];
                    $commit = Commit::query()->whereBelongsTo($user)->firstWhere('body', $attributes['body'])
                        ?? Commit::factory()->for($user)->make($attributes);
                    $commit->fill($attributes)->save();
                    $headline->commits()->syncWithoutDetaching($commit);

                    $this->updateCommitEmbedding->handle($commit);
                }
            }
        });
    }
}
