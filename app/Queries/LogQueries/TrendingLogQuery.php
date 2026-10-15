<?php

declare(strict_types=1);

namespace App\Queries\LogQueries;

use App\Contracts\Queries\LogQuery;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class TrendingLogQuery implements LogQuery
{
    private const int STAR_BOOST_IN_SECONDS = 6 * 60 * 60;

    private const int REPLY_BOOST_IN_SECONDS = 12 * 60 * 60;

    private const string STARS_FROM_OTHERS = 'select count(*) from stars'
        .' where stars.commit_id = commits.id and stars.user_id <> commits.user_id';

    private const string REPLIES_FROM_OTHERS = 'select count(*) from commits as replies'
        .' where replies.parent_id = commits.id and replies.user_id <> commits.user_id'
        ." and replies.status = '".CommitStatus::Approved->value."' and replies.deleted_at is null";

    private const string BOOST = self::STAR_BOOST_IN_SECONDS.' * ('.self::STARS_FROM_OTHERS.')'
        .' + '.self::REPLY_BOOST_IN_SECONDS.' * ('.self::REPLIES_FROM_OTHERS.')';

    /**
     * Ranks this week's commits by when they were made, moved forward six hours for each star and twelve for each reply
     * from someone other than the author. The rank never changes as time passes, only as engagement does, so pages stay stable.
     *
     * @return Builder<Commit>
     */
    public function builder(User $user): Builder
    {
        return Commit::query()
            ->approved()
            ->topLevel()
            ->where('commits.created_at', '>=', now()->subWeek())
            ->whereRaw(self::BOOST.' > 0')
            ->select('commits.*')
            ->selectRaw("cast(strftime('%s', commits.created_at) as integer) + ".self::BOOST.' as trending_at')
            ->withVisibleReplyCount($user)
            ->withSavedState($user)
            ->with(['user', 'linkPreview', 'images'])
            ->withStarsFor($user)
            ->orderByDesc('trending_at')
            ->orderByDesc('id');
    }
}
