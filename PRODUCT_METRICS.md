# Product metrics

How /dev/null measures whether it works: which metrics exist, exactly how each is counted, what was measured on purpose and what was left for later. The numbers live in the Operations console → Growth (`/operations/metrics`, admins only). Its Overview turns the same data into signals (what is failing, what to watch, what is fine), and Forecasts projects active users, signups, commits and replies two weeks ahead from the daily history.

The goal is to know where the product leaks and what to act on, not to collect data. The page follows AARRR (acquisition, activation, retention; referral is not measured yet, and there is no revenue), plus a few community health metrics.

Status: **implemented** metrics are on the Growth page. **Not yet** metrics are ideas kept for later, with what they would need.

## Principles

- **Store as little as possible.** Most metrics are computed from data the app already has (users, commits, stars, follows) and from Fathom, which is anonymous. Only three things were added for metrics: the last day each person was seen, one anonymous count of active people per day, and two anonymous Fathom events for the conversion rate.
- **No personal attribution.** Nobody's referrer, landing page or campaign is stored. Where people come from is read from Fathom, in aggregate.
- **Bots are left out** everywhere: Tom and @world (`User::BOT_GITHUB_IDS`).
- **Read trends, not thresholds, at first.** With a few days of history, metrics say little on their own. a16z notes that they only become predictive with a few thousand active users and several cohorts past day 30.

## What is stored

Each answers the three questions of the [data guidelines](.ai/guidelines/data.blade.php):

| Data | Where | Why the feature needs it | Who sees it | When it is deleted |
|---|---|---|---|---|
| The last day a person was seen (a date, no time) | `users.last_seen_at`, hidden | Retention and active users can't be derived from anything else: reading alone leaves no trace. Written at most once a day, without touching `updated_at`. | Admins, only as aggregates on the Growth page; the person in their export | With the account |
| The number of people seen each day | `active_user_counts` (`seen_at`, `count`), no user ids | The daily active users history: `last_seen_at` only keeps each person's latest day, so past days can't be recomputed. Incremented the first time each person is seen in a day, so the count is exact. | Admins, on the Growth page | Never: it is an anonymous count |
| Two anonymous events, `guest-visit` and `signup` | Fathom | The conversion rate, counted on both sides by the same tool. No user id is sent. | Admins, through Fathom | Per Fathom's retention |

**Seen** means signed in and opening a page or doing something (starring, replying, following) on the web or the installed app, or using the API from the mobile app or the terminal. Posts automated with the GitHub Action count too: someone who publishes, even automatically, is active. What a page reads on its own (polling for notifications, partial reloads, prefetches, JSON reads) doesn't count, or a tab left open would keep its owner active forever.

## The funnel (implemented)

The funnel follows the people who **signed up in the selected range** (the last 30 days by default, or all time). Each step shows its count, its rate, and the change of that rate in percentage points since the period before.

### Acquisition

- **Visitors**: people **not signed in** who opened dev-null.sh in the range, counted by Fathom: the browser sends a `guest-visit` event once per session to someone not signed in, and Fathom counts the people who sent it (`unique_conversions`).
- **Signups**: accounts created in the range, from the database. This is the absolute number.
- **Conversion rate** = people who sent a `signup` event ÷ people who sent a `guest-visit` event, both from Fathom. The browser sends `signup` once, on the page right after an account is created (the server flashes `signedUp`).
- Why both sides of the rate come from Fathom: Fathom is blocked by uBlock Origin, Brave and Firefox's tracking protection, which developers use a lot. Signups from the database over visitors from Fathom would overstate the rate by an unknown amount. With both from Fathom, ad blockers hide the same share of each, as long as people who sign up block Fathom as often as other visitors.
- Why only visitors not signed in: members browsing the site are not prospects; counting them would understate the rate.
- **Acquisition by day**: a chart of the signups of each day, from the database, or, toggled, of the conversion rate of each day, from Fathom (`date_grouping=day`). One at a time rather than both on one chart with two scales, so neither trend is drawn against the other's; the tooltip of a day tells both. Today's bar is lighter, as the day is still counting. A dashed line marks the average signups of the other days, or the conversion rate of the whole range. A day's Fathom counts are people on that day, so the days don't add up to the range: someone who visits on two days counts on both.
- Caveats:
  - The events only exist since the release that added them; the rate reads "–" before.
  - Fathom's API doesn't define `unique_conversions` precisely; check on real data that it counts people, not events.
  - A member signed out (another device, an expired session) counts as a visitor.
  - Events may count against Fathom's monthly quota, which is why `guest-visit` is sent once per session, not on every page.

### Activation

- **Activated**: within **12 hours** of signing up, the person did one of the things that best told the people who came back from the others (see "How activation is defined" below):
  - **Reader**: starred posts written by **2 or more different people** (not themselves, not bots).
  - **Follower**: followed **10 or more people** themselves (not bots). Follows imported from GitHub don't count, the same way as for "follows, first 24h".
  - **Author**: posted (a commit or a reply), and someone else (not a bot) replied to it, quoted it or starred it, all within the 12 hours.
- **Activation rate** = activated ÷ signups at least 12 hours old.
- **Activation by signup day**: a chart of the activation rate of each day's signups, to see whether a change to the product moves it. It is computed from existing data, so it goes back to the launch. A day's bar stays lighter until its last signups are 12 hours old, and a dashed line marks the activation rate of the whole range. It is not an A/B test: a launch or a viral post also changes who signs up, so read a trend over several days, not one bar.
- Why these choices:
  - Activation is an early signal of retention: it is measured within the first visit, so a change to onboarding shows the same day, where a return can only be seen the day after, too late to act on.
  - Posting alone does not count: for an author, the value is the reaction. Posting into the void brings 40% of people back, a reaction 63%.
  - A star is cheap: counting distinct authors instead of stars means the feed showed several interesting people, not one friend.
  - 12 hours approximates the first visit. Returning the next day is retention, not activation, and the two must not overlap.
- Caveat: the thresholds were measured on the first week of cohorts. Measure them again as cohorts grow (see "How activation is defined" below).

### Retention

- **Retained**: seen again **from day 1 after signing up, or later** ("unbounded" retention), as told by `last_seen_at`. Day 1 is temporary, while /dev/null is days old and changes day by day; the goal is day 14.
- **Retention rate** = retained ÷ signups whose day 1 is over. A signup whose day 1 is today isn't counted yet: they may still come back today.
- Why `last_seen_at` and not one row per person per day: it is one date per person, overwritten, with no history, which is the least data that answers "is this person still coming back after 14 days?".
- Caveats:
  - Unbounded retention is always higher than the bounded "active on day N" retention that public benchmarks use. Someone who comes back once on day 60 counts as retained at day 1. **Do not compare it with the a16z numbers below.**
  - A cohort's retention keeps rising a little as late visitors come back, so older cohorts look slightly better than recent ones.
  - Deleted accounts disappear from their cohort instead of counting as gone.

### Changes and the orange highlight

- **All time**: changes compare the **last 7 days with the 7 before**. Retention compares the signups whose day N is in the last 7 days with the 7 before, at the same day N.
- **A chosen range**: changes compare the range with **the same number of days just before it**, retention at the same day N.
- Changes are left out until the period before starts after the first signup: without history there is nothing to compare.
- **Orange** marks the rate that dropped the most. It is not the lowest rate: rates of different steps can't be compared (a conversion rate from anonymous visitors is always low), so a level only means something against its own past or its own benchmark.

## Also watch (implemented)

| Metric | Definition |
|---|---|
| Active today, 7 days, 30 days | People seen today, in the last 7 days and in the last 30 (DAU, WAU, MAU), UTC days. |
| Come daily | Active today ÷ active in 30 days (DAU/MAU). |
| Posted | Share of the people active in the last 7 days who wrote a commit or a reply in those 7 days (creators vs readers). |
| Commits answered | Share of approved top-level commits written in the range that got a reply, a quote or a star from someone else within 24 hours, with the median time to that first answer. Deleted commits count. |
| First posts answered | The same, for commits that were their author's first post of any kind. |
| Follows, first 24h | Median number of people a newcomer followed themselves in their first 24 hours. Follows imported from GitHub are left out: they are created in the transaction that records the import, so follows made in the 10 seconds before either side's import are taken for imported ones. |
| Followed back | Share of those follows that were followed back since. |
| Daily active users | A chart of the people seen each day of the range, from `active_user_counts`, each bar stacked from the bottom: returning users, then that day's signups who activated, didn't, or are under 12 hours old, too young to tell. Each day casts a shadow onto the next, from its total down to the next day's returning users: the drop is the net change, people who stopped coming minus people back after a break, since returning users may have been away for any number of days. It reads the funnel day by day: what makes the audience grow or shrink. Returning is the day's count minus its signups, as every signup is seen on the page it lands on, so it can't be split further, and it is not the retention rate: it grows on its own the day after a spike of signups. An account deleted since still counts in its day but no longer in the signups, so returning reads slightly high. Today's bar is still counting; a dashed line marks the average of the other days. |

## Benchmarks

For reference, with how comparable each one is. None of them was validated for a developer social network.

| Metric | Benchmark | Source | Comparable? |
|---|---|---|---|
| DAU/MAU | OK 25%, good 40%, great 50%+ (seed-stage consumer social) | [a16z](https://a16z.com/do-you-have-lightning-in-a-bottle-how-to-benchmark-your-social-app/) | Yes, same definition |
| Activation rate | Average 34%, median 25% (all products) | [Lenny's Newsletter](https://www.lennysnewsletter.com/p/what-is-a-good-activation-rate) | Partly: each product picks its own milestone, and 12 hours is strict |
| Retention, d1 / d7 / d30 (bounded) | OK 50/35/20%, good 60/40/25%, great 70/50/30% | [a16z](https://a16z.com/do-you-have-lightning-in-a-bottle-how-to-benchmark-your-social-app/) | **No**: ours is unbounded, and always higher |
| Retention at 6 months, consumer social | ~25% good, ~45% great | [Lenny's Newsletter](https://www.lennysnewsletter.com/p/what-is-good-retention-issue-29) | Later, and only bounded |
| Monthly user growth | OK 20%, good 35%, great 50%, ideally almost all organic | [a16z](https://a16z.com/do-you-have-lightning-in-a-bottle-how-to-benchmark-your-social-app/) | Yes, not on the page yet |
| L5+ (share of weekly actives present 5+ days) | OK 30%, good 40%, great 50%+ | [a16z](https://a16z.com/the-power-user-curve-the-best-way-to-understand-your-most-engaged-users/) | Not measurable (see below) |
| Participation | 90% read, 9% contribute a little, 1% most (2006, indicative) | [Nielsen Norman Group](https://www.nngroup.com/articles/participation-inequality/) | Roughly, against "Posted" |
| Visitor to signup conversion | No reliable benchmark found | — | — |

Starting targets, as hypotheses until /dev/null has its own baseline: activation rate at least 34%, DAU/MAU at least 25%. No target for retention or conversion until there is data. After about 4 weeks, replace these with the average of the first cohorts.

## How activation is defined

Activation is whatever, done in the first 12 hours, best tells the people who come back from the others. It is chosen from the data, not by taste:

1. **List candidate behaviors** of the first 12 hours: starring posts by 1, 2, 3 or 5 authors, following 1, 5 or 10 people, posting, getting an answer, voting, sending a message.
2. **For each one, compare who came back** from day 1: the share of signups who did it and came back, against the share of those who didn't. The wider the gap, the better the behavior predicts retention.
3. **Weigh it against how many people it covers.** A behavior only 2% of people do can't be the definition, however well they come back.
4. **Combine the best ones** into a few definitions and compare them the same way: activation rate, came back if activated, came back otherwise, and the ratio of the two.
5. **Pick the definition with the widest gap** that still covers a meaningful share, and prefer the simpler one when two are within a point or two: a week of cohorts can't tell them apart.
6. **Measure again** every few weeks, and once cohorts reach day 7 and day 14.

Facebook found "7 friends in 10 days" this way (Chamath Palihapitiya), and Twitter following about 30 accounts, a third to two thirds of them following back ([Josh Elman](https://mattermark.com/putting-it-all-together-how-josh-elman-identified-a-growth-driver-at-twitter/)).

Measured on 2026-10-23, over the 5,868 signups since 2026-10-19 whose day 1 was over (came back from day 1):

| Behavior in the first 12 hours | Share of signups | Came back if done | Came back if not |
|---|---|---|---|
| Starred posts by 5+ authors | 2.8% | 92.8% | 32.9% |
| Starred posts by 3+ authors | 5.0% | 85.4% | 31.9% |
| Starred posts by 2+ authors | 8.2% | 78.3% | 30.6% |
| Followed 10+ people themselves | 4.9% | 73.9% | 32.6% |
| Voted in a poll | 5.8% | 67.4% | 32.6% |
| Starred posts by 1+ author | 16.9% | 63.8% | 28.6% |
| Posted and got an answer | 24.6% | 60.5% | 26.1% |
| Posted | 30.7% | 56.8% | 24.8% |
| Followed 5+ people themselves | 16.9% | 54.7% | 30.5% |
| Imported follows from GitHub | 94.4% | 34.1% | 42.9% |
| Did none of these | 31.7% | 19.3% | 41.7% |

| Definition | Activation rate | Came back if activated | Otherwise | Ratio |
|---|---|---|---|---|
| Starred 3+ authors, or got an answer (before) | 25.1% | 60.4% | 25.9% | 2.33× |
| **Starred 2+ authors, or got an answer, or followed 10+ (now)** | 27.6% | 60.0% | 24.9% | 2.41× |
| Starred 1+ author, or got an answer, or followed 5+ | 35.0% | 53.3% | 24.5% | 2.18× |

A handful of accounts star or follow every newcomer. Leaving out the 5 that reached 500+ newcomers changed the ratio by less than a tenth, so they are kept: the people they answer come back 49% of the time, less than with a regular answer but more than with none.

## Not yet

| Metric | What it would tell | What it needs |
|---|---|---|
| Bounded retention (active on day 1, 7, 30) | A number comparable with public benchmarks | One row per person per active day, which was turned down to store less |
| Power user curve (L7, L5+) | Whether a daily core forms, cohort after cohort | The same per-day activity |
| Quick ratio and growth accounting | (new + resurrected) ÷ churned: whether growth hides a leaking bucket ([Social Capital, via](https://davidjaxon.wordpress.com/2016/03/15/a-simple-metric-to-measure-the-quality-of-your-user-growth/)) | A few weeks of history; best with per-day activity |
| Resurrection rate | Share of people away 14+ days who come back | A few weeks of history |
| Retention by core action | Whether people come back to post, star or reply, not only to look | Computable from existing data |
| Time to first follow and first post | How fast onboarding gets people going | Computable from existing data |
| Attention concentration | Share of stars received by the top 1% of authors | Computable from existing data |
| Conversation depth | Share of commits whose thread has 2+ people besides the author | Computable from existing data |
| Signups by source | Which channels bring people who stay | Per-person attribution, turned down for privacy. A Fathom event on "Sign in with GitHub" would give an anonymous approximation |
| Monthly growth on the page | The a16z growth benchmark | Computable from signups and active users |
| Referral | Whether members bring people in, not only the team | Knowing who shared the link, not whose content it points to (see below) |

## Decisions

Taken on 2026-10-19, when /dev/null was a few days old, had about 6,000 people and gained about 1,000 a day.

- Retention matters more than growth: active users level off at daily signups × average active lifetime, and the loops that bring people in run on the people who stay.
- Activation counts value received within 12 hours, as a reader or as an author; posting alone is not activation.
- On 2026-10-23, activation was measured against day-1 returns for the first time (see "How activation is defined"). It moved from 3 starred authors to 2, and gained following 10+ people: a wider definition with the same power to tell who comes back.
- Retention is unbounded, from `last_seen_at`, headline day 14, with a fallback to day 7 and day 1 meanwhile.
- On 2026-10-20, retention was set to day 1 alone, without the fallback: /dev/null was days old and changed day by day, so the next day is what mattered. Moving to day 14 means bringing back the fallback to day 7 and day 1, from the history of `ProductFunnelQuery`.
- No per-person referrer or source: Fathom covers acquisition, anonymously.
- Signups are counted from the database; the conversion rate is counted entirely by Fathom, so ad blockers cancel out, and only over visitors not signed in.
- Daily active users come from `last_seen_at`, not from Fathom, which ad blockers hide and which can't see the mobile app.
- The highlight follows the trend, not the level, since the steps can't be compared with each other.
- Referral was dropped on 2026-10-20, a day after it shipped. It split outside visits starting on a profile or a commit between the team's and the members'. Signed-in members browsing each other's profiles made it read 99%, and even counting only people not signed in, it measures whose content a visit lands on, not who shared it: when the team tweets a member's profile, the visit counts for the member. A later version could attribute visits to whoever shared the link, with the copy-link and share buttons adding `?via=<username>` and an anonymous Fathom event telling the team from members. It would only see links shared with those buttons.

## Sources

- [a16z, How to benchmark your social app](https://a16z.com/do-you-have-lightning-in-a-bottle-how-to-benchmark-your-social-app/)
- [a16z, The power user curve](https://a16z.com/the-power-user-curve-the-best-way-to-understand-your-most-engaged-users/)
- [a16z, 16 ways to measure network effects](https://a16z.com/16-ways-to-measure-network-effects/)
- [Lenny's Newsletter, What is a good activation rate](https://www.lennysnewsletter.com/p/what-is-a-good-activation-rate)
- [Lenny's Newsletter, What is good retention](https://www.lennysnewsletter.com/p/what-is-good-retention-issue-29)
- [Nielsen Norman Group, Participation inequality](https://www.nngroup.com/articles/participation-inequality/)
- [CHAOSS, Time to first response](https://chaoss.community/kb/metric-time-to-first-response/)
- [Josh Elman on Twitter's growth driver](https://mattermark.com/putting-it-all-together-how-josh-elman-identified-a-growth-driver-at-twitter/)
- [Quick ratio (Social Capital), as quoted by David Jaxon](https://davidjaxon.wordpress.com/2016/03/15/a-simple-metric-to-measure-the-quality-of-your-user-growth/)
- [Fathom API](https://usefathom.com/api), for event `unique_conversions`
- [SealMetrics, analytics tools against ad blockers](https://sealmetrics.com/blog/analytics-tools-ad-blocker-test/) (a competitor's test) and [Fathom on custom domains](https://usefathom.com/changelog/mar2023-firewall-settings)
