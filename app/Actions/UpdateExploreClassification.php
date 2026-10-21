<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\ExploreTopic;
use App\Queries\ExploreContentQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class UpdateExploreClassification
{
    public function __construct(private ExploreContentQuery $content)
    {
        //
    }

    /**
     * @param  Collection<int, Commit>  $commits
     * @param  array<string, ExploreTopic>  $catalog
     * @param  array{topics: list<array{ref: string, kind: string, key: string, title: string, categories: list<string>, subjects: list<string>, aliases: list<string>}>, assignments: list<array{ref: string, topics: list<string>}>}  $data
     */
    public function handle(Collection $commits, array $catalog, array $data): void
    {
        DB::transaction(function () use ($commits, $catalog, $data): void {
            $eligible = $this->content->eligible()->whereKey($commits->pluck('id'))->lockForUpdate()->pluck('id');
            $assignments = collect($data['assignments'])->filter(fn (array $row): bool => $eligible->contains($commits->get((int) mb_substr($row['ref'], 1))?->id));
            $used = $assignments->pluck('topics')->flatten()->unique();
            $definitions = collect($data['topics'])->keyBy('ref');
            $used = $used->merge($used->flatMap(fn (string $ref): array => $definitions->get($ref)['subjects'] ?? []))->unique();
            foreach ($definitions->filter(fn (array $definition): bool => $used->contains($definition['ref']))->sortBy('kind') as $definition) {
                $key = Str::slug(mb_ltrim($definition['key'], '#'));
                $existing = $definition['kind'] === 'subject'
                    ? DB::table('explore_topic_aliases')->where('alias', $key)->value('topic_id') : null;
                $topic = is_string($existing) ? ExploreTopic::query()->findOrFail($existing)
                    : ExploreTopic::query()->firstOrCreate(['kind' => $definition['kind'], 'key' => $key], [
                        'title' => $definition['title'], 'categories' => $definition['categories'], 'summary_dirty_at' => now(),
                    ]);
                $catalog[$definition['ref']] = $topic;
                if ($topic->kind === 'subject') {
                    foreach ([$key, ...$definition['aliases']] as $alias) {
                        DB::table('explore_topic_aliases')->insertOrIgnore(['alias' => Str::slug(mb_ltrim($alias, '#')), 'topic_id' => $topic->id]);
                    }
                }
            }

            foreach ($definitions->filter(fn (array $definition): bool => $used->contains($definition['ref']) && $definition['kind'] === 'story') as $definition) {
                foreach ($definition['subjects'] as $ref) {
                    DB::table('explore_topic_subject')->insertOrIgnore(['story_id' => $catalog[$definition['ref']]->id, 'subject_id' => $catalog[$ref]->id]);
                }
            }

            foreach ($assignments as $row) {
                $id = $commits->get((int) mb_substr($row['ref'], 1))?->id;
                foreach ($row['topics'] as $ref) {
                    $topic = $catalog[$ref];
                    DB::table('explore_commit_topic')->insertOrIgnore(['topic_id' => $topic->id, 'commit_id' => $id]);
                    DB::table('explore_summary_sources')->insertOrIgnore(['topic_id' => $topic->id, 'commit_id' => $id]);
                    $parents = DB::table('explore_topic_subject')->where('story_id', $topic->id)->pluck('subject_id')->all();
                    ExploreTopic::query()->whereKey([$topic->id, ...$parents])->update(['summary_dirty_at' => now(), 'summary_revision' => DB::raw('summary_revision + 1')]);
                }

                DB::table('explore_classification_work')->where('commit_id', $id)->update(['completed_at' => now(), 'claimed_at' => null]);
            }
        });
    }
}
