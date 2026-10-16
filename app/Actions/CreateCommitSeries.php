<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\SeriesPosition;
use App\Enums\CommitStatus;
use App\Jobs\EvaluateCommitJob;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\ModerationRule;
use App\Models\User;
use App\Services\TypeSafe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateCommitSeries
{
    public const int MAX_PARTS = 10;

    public function __construct(
        private CreateCommit $createCommit,
        private TypeSafe $typeSafe,
    ) {
        //
    }

    /**
     * Each part is a reply to the part before it, so the series reads as one thread. Images go with the first part.
     * Automatic moderation evaluates the whole series once every link preview is fetched.
     *
     * @param  list<string>  $bodies
     * @param  list<UploadedFile>  $images
     * @return Collection<int, Commit>
     */
    public function handle(User $user, array $bodies, array $images = []): Collection
    {
        $size = count($bodies);

        return DB::transaction(function () use ($user, $bodies, $images, $size): Collection {
            $seriesId = Str::uuid7()->toString();
            $parts = new Collection;

            foreach ($bodies as $index => $body) {
                $parts->push($this->createCommit->handle(
                    $user,
                    $body,
                    $parts->last(),
                    $index === 0 ? $images : [],
                    new SeriesPosition($seriesId, $index + 1, $size),
                ));
            }

            $jobs = $parts->load('linkPreview')
                ->map(fn (Commit $part): ?LinkPreview => $part->linkPreview)
                ->filter(fn (?LinkPreview $preview): bool => $preview instanceof LinkPreview && $preview->fetched_at === null)
                ->unique('id')
                ->map(fn (LinkPreview $preview): UnfurlLink => new UnfurlLink($preview))
                ->values()
                ->all();

            $first = $parts->first();

            if ($first instanceof Commit && $first->status === CommitStatus::Pending
                && $this->typeSafe->isEnabled() && ModerationRule::query()->active()->exists()) {
                $jobs[] = new EvaluateCommitJob($first);
            }

            if ($jobs !== []) {
                DB::afterCommit(fn () => Bus::chain($jobs)->dispatch());
            }

            return $parts;
        });
    }
}
