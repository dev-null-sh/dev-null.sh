<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Scores now weigh stars, quotes, replies and replies the author answers each with their own weight, summed for
     * each developer instead of counting each developer once for the most they did, and double every six hours instead
     * of three. Every stored score is recounted, or any commit rescored after the deploy would rank on another scale
     * than every commit that was not. The formula is copied, so this migration keeps filling the same scores whatever
     * the app becomes, and the scores are computed from scratch, so running it twice gives the same result.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            with stargazers as (
                select commit_id as thread_id, user_id from stars
                union
                select starred.root_id, stars.user_id from stars
                join commits as starred on starred.id = stars.commit_id
                where starred.root_id is not null and starred.status = 'approved' and starred.deleted_at is null
            ),
            quoters as (
                select quoted_commit_id as thread_id, user_id from commits
                where quoted_commit_id is not null and status = 'approved' and deleted_at is null
                union
                select quoted.root_id, quotes.user_id from commits as quotes
                join commits as quoted on quoted.id = quotes.quoted_commit_id
                where quoted.root_id is not null and quotes.status = 'approved' and quotes.deleted_at is null and quoted.status = 'approved'
            ),
            repliers as (
                select distinct root_id as thread_id, user_id from commits
                where root_id is not null and status = 'approved' and deleted_at is null
            ),
            repliers_answered_by_author as (
                select distinct replies.root_id as thread_id, replies.user_id from commits as replies
                join commits as roots on roots.id = replies.root_id
                join commits as answers on answers.parent_id = replies.id and answers.user_id = roots.user_id
                where replies.status = 'approved' and replies.deleted_at is null and answers.status = 'approved' and answers.deleted_at is null
            ),
            engagements as (
                select thread_id, user_id, 0.5 as weight from stargazers
                union all
                select thread_id, user_id, 1.0 from quoters
                union all
                select thread_id, user_id, 13.5 from repliers
                union all
                select thread_id, user_id, 75.0 from repliers_answered_by_author
            ),
            weighted as (
                select roots.id, sum(engagements.weight) as engagement
                from commits as roots join engagements on engagements.thread_id = roots.id
                where roots.parent_id is null and engagements.user_id is distinct from roots.user_id
                group by roots.id
            )
            update commits set hot_score = round((
                log(2, 1 + coalesce(weighted.engagement, 0))
                + extract(epoch from commits.created_at)::numeric / 21600
            ), 6)
            from commits as roots
            left join weighted on weighted.id = roots.id
            where commits.id = roots.id and roots.parent_id is null and roots.deleted_at is null
            SQL);
    }
};
