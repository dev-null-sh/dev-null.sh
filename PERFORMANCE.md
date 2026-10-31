# Performance investigation — 2026-10-27

Measurement baseline: `fb05d164` (`chore: deps`). The implementation is rebased onto upstream `85376fd8845015803d74c802bae0811d2849b025`, including #1220’s automatic TSX discovery. No database, query, migration, dependency-version or request-polling changes.

## Measured change

Shared timestamp helpers created date/time formatters for each call. Feed cards, quoted commits, notifications, chat messages, scheduling and profile clocks use these helpers during browser rendering and SSR. Reuse formatter objects, keyed by their complete options, in a FIFO cache capped at 128 entries. Cache the fixed English relative-time formatter too. Dates and rendered labels are never cached; offsets continue to follow DST and each call uses the current reader's time zone/hour cycle. The formerly unbounded calendar-day formatter cache now shares the same bound.

Local Node v22.16.0, macOS arm64; fixed dates and clock; ten warm-up batches, then median of 31 samples, alternating old/new order. Each sample formats 100 items. These are **helper CPU timings**, not React render, page-load, network, Core Web Vitals or production server latency measurements.

| Workload | Original | Updated | Reduction |
| --- | ---: | ---: | ---: |
| Feed: relative label + full timestamp per card | 8.121 ms | 0.834 ms | 89.7% |
| Chat: clock time per message | 2.730 ms | 0.111 ms | 95.9% |
| Profile clocks: zoned time + UTC offset | 5.761 ms | 0.390 ms | 93.2% |

These warm-cache results do not establish cold-start improvements. Absolute times vary by runtime/hardware. Do not enforce wall-clock thresholds in CI. The benchmark checks identical output for both versions before timing each workload.

## Reproduce

Install locked frontend dependencies with `bun install --frozen-lockfile`. From the repository root:

```sh
mkdir -p node_modules/.cache/performance
git show fb05d164:resources/js/lib/utils.ts > node_modules/.cache/performance/baseline-utils.ts
bun build node_modules/.cache/performance/baseline-utils.ts --target=node --packages=external --outfile=node_modules/.cache/performance/baseline-utils.mjs
node --experimental-strip-types scripts/performance/timestamps.ts "$PWD/node_modules/.cache/performance/baseline-utils.mjs"
node --experimental-strip-types --test tests/Unit/ReaderClock.test.ts
bunx vp lint --no-ignore resources/js/lib/utils.ts tests/Unit/ReaderClock.test.ts scripts/performance/timestamps.ts
bunx tsc --noEmit --project scripts/performance/tsconfig.json
```

The original module lives in the ignored dependency directory so package imports resolve. Compile it to JavaScript because Node does not strip TypeScript in node_modules. The script requires a baseline argument to prevent accidentally comparing the updated code with itself.

## Regression checks and limits

The clock suite covers 145 time-zone entries (including revisits after cache eviction), all four hour cycles, year rollover, DST boundaries, invalid-date labels, half/quarter-hour offsets, switching reader clocks, and synced-ago boundaries. More than 20,000 exact native-output comparisons pass. Existing tests also cover calendar-day labels and explicitly selected zones.

Broader checks were run against both original and updated utils:

- Standalone TypeScript suite: final run has 199 passing tests and the same two failing test files seen with the original helper. `RemotePushAccountIsolation.test.ts` imports `worker_threads.locks`, unavailable in local Node 22.16. `SeriesStash.test.ts` launches the TSX suite, which fails on missing generated routes.
- TSX suite: identical original/updated results, 17 passing files and 33 failing files; 74 passing tests and one failing test. Failures reference missing generated Laravel controller/route modules.
- Full application typecheck: blocked by missing generated Laravel routes. Targeted type-aware lint/typecheck covers the changed helper, regression tests and benchmark.
- PHP dependency install: the locked security advisory rejects local PHP 8.5.7; it requires at least 8.5.12 in that release line. No security checks were bypassed and no lockfile was updated.

Consequently, local production builds, PHP tests and authenticated end-to-end/browser performance measurements remain unverified. Public guest production traces are recorded below; they do not validate the local patch. This change is ready for review, but a clean full regression run on supported PHP/Node with generated Wayfinder files is still required before merging. Do not interpret unchanged environment failures as a fully green suite.

## Existing upstream PRs

Read from `dev-null-sh/dev-null.sh` on 2026-10-27. Relevant existing work was intentionally not duplicated:

| PR | Status | Scope |
| --- | --- | --- |
| [#1205](https://github.com/dev-null-sh/dev-null.sh/pull/1205) | Open | Lazy lightbox/calendar loading; also changes composer imports. Natural first-load follow-up. |
| [#340](https://github.com/dev-null-sh/dev-null.sh/pull/340) | Open | Photo-viewer lazy loading; overlaps #1205. |
| [#1130](https://github.com/dev-null-sh/dev-null.sh/pull/1130) | Open | Guest notification polling and Product Hunt banner timers. |
| [#1150](https://github.com/dev-null-sh/dev-null.sh/pull/1150) | Merged, present locally | SSR hydration rather than rebuilding existing markup. |
| [#347](https://github.com/dev-null-sh/dev-null.sh/pull/347) | Merged | Parser fast paths and commit-body memoization. |
| [#473](https://github.com/dev-null-sh/dev-null.sh/pull/473), [#680](https://github.com/dev-null-sh/dev-null.sh/pull/680) | Open | Notification grouping/query work; overlapping approaches need review. |
| [#448](https://github.com/dev-null-sh/dev-null.sh/pull/448), [#506](https://github.com/dev-null-sh/dev-null.sh/pull/506) | Open | Org membership / remote access query batching. |
| [#271](https://github.com/dev-null-sh/dev-null.sh/pull/271) | Open | Sitemap XML rendering. |

PR titles are not evidence that their reported gains apply to this checkout. This investigation did not merge or benchmark those PRs. Database-related PRs are recorded for awareness only.

## Next measurements

On a runnable production build with representative data, measure cold/warm guest feed, authenticated feed, long-thread and Remotes navigation: transferred JS, LCP, INP, layout shifts, SSR CPU and request p50/p95. Use identical data, hardware and throttling before/after. Profile rendering and scrolling before choosing further changes. Evaluate #1205 and #1130 separately to attribute gains and check photo, scheduling, notification and navigation behavior. Database tuning should follow evidence from these profiles rather than speculative changes.


## Additional easy gains

The same local Node runtime, 31-sample medians, alternating original/updated order; both versions built with the same Vite settings. Raw results: [hot-paths.node22.json](scripts/performance/results/hot-paths.node22.json). No network or database work is included in these helper timings.

| Workload | Original | Updated | Reduction |
| --- | ---: | ---: | ---: |
| Graph: 100 replies | 0.09567 ms | 0.02311 ms | 75.8% |
| Graph: 500 replies | 1.99698 ms | 0.11020 ms | 94.5% |
| Graph: 2,000 replies (stress test) | 35.65110 ms | 0.54689 ms | 98.5% |
| Graph: 500 trunk-only nodes (control) | 0.05059 ms | 0.04906 ms | approximately unchanged |
| Emoji search, `heart` | 0.09212 ms | 0.03109 ms | 66.3% |
| Emoji search, empty query | 0.66144 ms | 0.53950 ms | 18.4% |
| Inbox search: 1,000 conversations | 3.21808 ms | 0.05628 ms | 98.3% |
| Certificate check: 100 highlighted lines, no fingerprint | 0.07225 ms | 0.00090 ms | 98.8% |
| Certificate check: plain fingerprint present (control) | 0.00033 ms | 0.00023 ms | 28.9% |
| Chat newest-25 boundary: 1,000 messages | 0.00422 ms | 0.00007 ms | 98.3% |

Graph layout builds branch indexes lazily and sorts siblings once. It preserves node/edge order, equal-height ties and drawing-trunk behavior. The trunk-only control initially regressed; moving index construction into the branch path eliminated that regression. Its small measured difference should be treated as noise.

Emoji search precomputes shortcode entries and word boundaries, keeping ranking, aliases and usage preferences unchanged. Inbox search uses a Set for deduplication instead of a repeated linear search. Certificate checks avoid splitting highlighted HTML when no fingerprint is present, and handle plain text directly. Chat finds the 25th newest actual message from the end, avoiding old-history scans and intermediate arrays. **The chat benchmark stubs DOM geometry and CSS escaping**; it measures the boundary-selection work, not actual browser scroll latency. Large percentage improvements on tiny functions do not imply equivalent whole-app improvements.

The shared relative-time timer also stops while the document is hidden, updates immediately on return and removes its visibility listener when the last subscriber leaves. Deterministic fake-timer tests verify **120 hidden timer ticks per hour become zero**. This changes no HTTP polling.

Additional verification passes: 59 focused Node tests; 16 focused TSX tests (emoji, timer lifecycle, LCP image SSR markup); exact old/new edge equality for 300 seeded generated graph layouts; exact emoji-result equality for 3,096 distinct queries with usage preferences. The broader standalone run passed 203 tests with the same two environment-related failures recorded above. Pure-helper typechecking and targeted type-aware lint pass. Lint/typechecking of changed page components remains blocked by absent generated Wayfinder modules, so those are not claimed green.

Reproduce:

```sh
node scripts/performance/prepare-hot-paths.mjs fb05d164
node --experimental-strip-types scripts/performance/hot-paths.ts
node --experimental-strip-types --test tests/Unit/GraphEdges.test.ts tests/Unit/ChatScroll.test.ts tests/Unit/CertificateHashes.test.ts tests/Unit/ConversationSearch.test.ts tests/Unit/ReaderClock.test.ts
bunx vp test run --config scripts/performance/vite.config.ts
```

The LCP regression suite lives under `tests/Unit` and is included automatically by the standard runner and test typecheck after upstream #1220 merged. The isolated performance config provides a focused shortcut; it does not replace normal CI coverage. Graph visual PRs [#1169](https://github.com/dev-null-sh/dev-null.sh/pull/1169), [#1147](https://github.com/dev-null-sh/dev-null.sh/pull/1147) and [#1126](https://github.com/dev-null-sh/dev-null.sh/pull/1126) touch the same file, but their diffs do not change `graphEdges`; review/rebase when integrating them. None of the open PRs returned in the refreshed queue changed the other optimized helper files.

## LCP investigation

Read-only Chrome DevTools MCP traces of the public production homepage on 2026-10-27, in isolated headless Chrome. The MCP server was started temporarily; no persistent editor configuration was changed. No authentication or production mutation was performed. Summary: [lcp.production.json](scripts/performance/results/lcp.production.json).

| Trace | LCP | TTFB | Resource load delay | Resource duration | Render delay | CLS |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Desktop, unthrottled, warm reload | 786 ms | 708 ms | n/a (text) | n/a | 78 ms | 0.067 |
| Mobile, 390×844 @3x, Slow 4G, 4× CPU, fresh visit | 7,524 ms | 564 ms | 2,859 ms | 2,007 ms | 2,094 ms | 0 |

These are single lab observations, **not field percentiles or a before/after comparison**. Feed content changes between visits. The desktop LCP was text; the mobile LCP was the first card's uploaded photo. The desktop trace used a service worker on reload; the mobile trace began recording on about:blank before its first navigation, with no service-worker interception of its LCP image. INP was not measured because no user interaction was performed.

The mobile image started at Low priority, was requested 3,423 ms after navigation, completed at 5,430 ms, and painted at 7,524 ms. Source and production HTML confirm that photos are lazy-loaded and hidden with `opacity-0` until React handles their load state. This creates two avoidable dependencies for an above-the-fold image: waiting for lazy-load eligibility and waiting for JavaScript to reveal already-downloaded pixels. Google's [LCP optimization guidance](https://web.dev/articles/optimize-lcp) describes both late discovery and hidden-until-JavaScript rendering as causes of LCP delay.

Local fix: only the first photo of the first log card (or its pinned card), and the focused commit's first photo, receives eager loading and high fetch priority. That photo is visible in the server HTML and does not mount the loading overlay. Other photos retain lazy loading and their existing placeholders. Dimensions, alternative text, photo navigation and error fallback are preserved. SSR markup tests verify that a two-photo grid promotes exactly one image. The priorities are deliberately limited to the log and focused commit; profile feeds with large headers are not made eager indiscriminately.

**No post-change page-level LCP gain is claimed**: production runs the deployed bundle, and the local app still cannot build/run until the PHP/Wayfinder setup is fixed. The 2,859 ms discovery delay and 2,094 ms render delay are diagnostic measurements, not promised savings: other work can become critical once these gates are removed. Test cold/warm visits, photos failing to load, navigation, hydration and collage layouts against a built patch before merging.

Other evidence: the homepage preloads all six configured font weights. Both desktop and mobile traces estimated zero LCP savings from the render-blocking CSS insight, so no blanket CSS deferral or preload removal was applied. The desktop document latency dominated that run, but this alone does not establish a database bottleneck; no database changes were made. PR #1205 remains relevant to reducing eager JS during hydration.


## Tab-switching investigation and review

The main log page explicitly disables tab prefetching (`resources/js/pages/log/show.tsx`) and flushes the entire prefetch cache when the log changes. This preserves correctness today: a GET of a different log currently persists the user's selected log in `LogController::show`, so a speculative fetch would otherwise overwrite that preference before the tab is actually opened. Enabling prefetch alone is unsafe. The helper optimizations above reduce work after a response arrives; they do not remove this network round trip.

Existing open [#755](https://github.com/dev-null-sh/dev-null.sh/pull/755) addresses safe log prefetch, instant navigation/loading states and saving displayed-log preferences separately; [#953](https://github.com/dev-null-sh/dev-null.sh/pull/953) addresses immediate profile-tab feedback and avoiding a whole-page view transition. They should be reviewed/rebased for current polling, the README profile tab, failed/cancelled requests, rapid switches, stale cached responses, back/forward, guest Following redirects and remembering the final displayed log. No duplicate navigation implementation is included here, and their reported responsiveness was not used as a benchmark of this patch. Moving an existing preference save is different from tuning or changing the database schema; this patch does neither.

Independent review identified one coverage issue: the new LCP unit suite was initially only in a manual runner. It was moved into the standard unit-test directory and is now covered by upstream #1220's test and typecheck globs. Revisions after the fix require exact-head independent approval before publication.

Required repository commands were attempted: `composer test:lint`, `composer test:type-coverage`, `bun run test:unit` and the full `vendor/bin/pest --parallel --ci --coverage --exactly=100.0` command. They cannot run without the PHP vendor dependencies. `bun run test:lint` passes resource formatting, then fails on missing generated Wayfinder imports; app and test typechecks show missing generated module errors. These are recorded as blockers, not passes. No Docker runtime or additional dependency installation was attempted after the user's disk-space constraint.
