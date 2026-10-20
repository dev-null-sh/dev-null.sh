# Product metrics

How /dev/null measures whether it works: which metrics exist, exactly how each is counted, what was measured on purpose and what was left for later. The numbers live in Operations → Metrics (`/operations/metrics`, admins only).

The goal is to know where the product leaks and what to act on, not to collect data. The page follows AARRR (acquisition, activation, retention, referral; there is no revenue), plus a few community health metrics.

Status: **implemented** metrics are on the Metrics tab. **Not yet** metrics are ideas kept for later, with what they would need.

## Principles

- **Store as little as possible.** Most metrics are computed from data the app already has (users, commits, stars, bookmarks, follows) and from Fathom, which is anonymous. Only three things were added for metrics: the last day each person was seen, one anonymous count of active people per day, and two anonymous Fathom events for the conversion rate.
- **No personal attribution.** Nobody's referrer, landing page or campaign is stored. Where people come from is read from Fathom, in aggregate.
- **Bots are left out** everywhere: Tom and @world (`User::BOT_GITHUB_IDS`).
- **The team is the admins** (`ADMINS`), when a metric needs to tell the team's content from everyone else's.
- **Read trends, not thresholds, at first.** With a few days of history, metrics say little on their own. a16z notes that they only become predictive with a few thousand active users and several cohorts past day 30.

## What is stored

Each answers the three questions of the [data guidelines](.ai/guidelines/data.blade.php):

| Data | Where | Why the feature needs it | Who sees it | When it is deleted |
|---|---|---|---|---|
| The last day a person was seen (a date, no time) | `users.last_seen_at`, hidden | Retention and active users can't be derived from anything else: reading alone leaves no trace. Written at most once a day, without touching `updated_at`. | Admins, only as aggregates on the Metrics tab; the person in their export | With the account |
| The number of people seen each day | `active_user_counts` (`seen_at`, `count`), no user ids | The daily active users history: `last_seen_at` only keeps each person's latest day, so past days can't be recomputed. Incremented the first time each person is seen in a day, so the count is exact. | Admins, on the Metrics tab | Never: it is an anonymous count |
| Two anonymous events, `guest-visit` and `signup` | Fathom | The conversion rate, counted on both sides by the same tool. No user id is sent. | Admins, through Fathom | Per Fathom's retention |

**Seen** means signed in and opening a page or doing something (starring, replying, following) on the web or the installed app, or using the API from the mobile app or the terminal. Posts automated with the GitHub Action count too: someone who publishes, even automatically, is active. What a page reads on its own (polling for notifications, partial reloads, prefetches, JSON reads) doesn't count, or a tab left open would keep its owner active forever.

Tracking started with the release that added it: visits before that left no trace, and the per-day counts start on that day.

## The funnel (implemented)

The funnel follows the people who **signed up in the selected range** (all time by default). Each step shows its count, its rate, and the change of that rate in percentage points since the period before.

### Acquisition

- **Visitors**: people **not signed in** who opened dev-null.sh in the range, counted by Fathom: the browser sends a `guest-visit` event once per session to someone not signed in, and Fathom counts the people who sent it (`unique_conversions`).
- **Signups**: accounts created in the range, from the database. This is the absolute number.
- **Conversion rate** = people who sent a `signup` event ÷ people who sent a `guest-visit` event, both from Fathom. The browser sends `signup` once, on the page right after an account is created (the server flashes `signedUp`).
- Why both sides of the rate come from Fathom: Fathom is blocked by uBlock Origin, Brave and Firefox's tracking protection, which developers use a lot. Signups from the database over visitors from Fathom would overstate the rate by an unknown amount. With both from Fathom, ad blockers hide the same share of each, as long as people who sign up block Fathom as often as other visitors.
- Why only visitors not signed in: members browsing the site are not prospects; counting them would understate the rate.
- Caveats:
  - The events only exist since the release that added them; the rate reads "–" before.
  - Fathom's API doesn't define `unique_conversions` precisely; check on real data that it counts people, not events.
  - A member signed out (another device, an expired session) counts as a visitor.
  - Events may count against Fathom's monthly quota, which is why `guest-visit` is sent once per session, not on every page.

### Activation

- **Activated**: within **12 hours** of signing up, the person got something from the network, as a reader or as an author:
  - **Reader**: starred or saved commits written by **3 or more different people** (not themselves, not bots).
  - **Author**: posted (a commit or a reply), and someone else (not a bot) replied to it, quoted it or starred it, all within the 12 hours.
- **Activation rate** = activated ÷ signups at least 12 hours old.
- Why these choices:
  - Activation means *receiving* value. Posting is giving value; for an author, the value is the reaction. Someone who posts into the void is the most likely to leave, so posting alone does not count.
  - A star is cheap: counting distinct authors instead of stars means the feed showed several interesting people, not one friend.
  - 12 hours approximates the first visit. Returning the next day is retention, not activation, and the two must not overlap.
- Caveat: it is a hypothesis. It is right if activated people are retained more than the others; check it once cohorts reach day 14 (see "Validating activation" below).

### Retention

- **Retained**: seen again **from day 14 after signing up, or later** ("unbounded" retention), as told by `last_seen_at`.
- **Retention rate** = retained ÷ signups whose day 14 is over. A signup whose day 14 is today isn't counted yet: they may still come back today.
- Until signups get through day 14, the page falls back to **day 7**, then **day 1**, and says from which day day 14 is available. Each number keeps a precise meaning, and the page switches by itself.
- Only signups whose day N falls on or after the day tracking started count: someone who came back before tracking started left no trace.
- Why `last_seen_at` and not one row per person per day: it is one date per person, overwritten, with no history, which is the least data that answers "is this person still coming back after 14 days?".
- Caveats:
  - Unbounded retention is always higher than the bounded "active on day N" retention that public benchmarks use. Someone who comes back once on day 60 counts as retained at day 14. **Do not compare it with the a16z numbers below.**
  - A cohort's retention keeps rising a little as late visitors come back, so older cohorts look slightly better than recent ones.
  - Deleted accounts disappear from their cohort instead of counting as gone.

### Referral

- **Referral**: visits from outside that **start on a member's profile or commit**, rather than on the team's (the admins). When it rises, members bring people in, not us.
- **Referral rate** = visits starting on a member's profile or commit ÷ visits starting on any profile or commit.
- Source: Fathom, filtered by entry page with a regular expression (`^/@[^/]+(/commits/[^/]+)?/?$` for all profiles and commits, the same restricted to the admins' usernames for the team). Direct visits count: links shared in Slack, Discord or messaging apps often lose their referrer, and nobody types a commit's id by hand.
- Why it is not a slice of the funnel: it counts **visits**, from every cohort, not people of the signup cohort. Drawn as the next slice, it would read "x% of retained people referred someone", which is not measured (that would need to store who brought whom).
- Not measured: whether those visitors sign up. Fathom doesn't link a visit to a signup.

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
| Daily active users | A chart of the people seen each day of the range, from `active_user_counts`. Today's bar is still counting. |

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

## Validating activation

Activation is right if it predicts retention. Once cohorts reach day 14, compare the retention of activated and non-activated signups, and of each path (reader, author). Then compare the distribution of follows in the first 24 hours between retained and gone people, to find /dev/null's own "7 friends in 10 days" threshold (Facebook's, from Chamath; Twitter's was following about 30 accounts, a third to two thirds of them following back, per [Josh Elman](https://mattermark.com/putting-it-all-together-how-josh-elman-identified-a-growth-driver-at-twitter/)). Adjust the definition, the window or the threshold of 3 authors if another one predicts better.

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

## Decisions

Taken on 2026-10-19, when /dev/null was a few days old, had about 6,000 people and gained about 1,000 a day.

- Retention matters more than growth: active users level off at daily signups × average active lifetime, and the loops that bring people in run on the people who stay.
- Activation counts value received within 12 hours, as a reader or as an author; posting alone is not activation.
- Retention is unbounded, from `last_seen_at`, headline day 14, with a fallback to day 7 and day 1 meanwhile.
- No per-person referrer or source: Fathom covers acquisition and referral, anonymously.
- Signups are counted from the database; the conversion rate is counted entirely by Fathom, so ad blockers cancel out, and only over visitors not signed in.
- Daily active users come from `last_seen_at`, not from Fathom, which ad blockers hide and which can't see the mobile app.
- The highlight follows the trend, not the level, since the steps can't be compared with each other.

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
- [Fathom API](https://usefathom.com/api), for event `unique_conversions`, `visits` and entry page filters
- [SealMetrics, analytics tools against ad blockers](https://sealmetrics.com/blog/analytics-tools-ad-blocker-test/) (a competitor's test) and [Fathom on custom domains](https://usefathom.com/changelog/mar2023-firewall-settings)
