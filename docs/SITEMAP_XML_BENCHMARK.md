# Commit sitemap XML rendering benchmark

## Baseline and scope

Upstream [#309](https://github.com/nunomaduro/dev-null.sh/pull/309) already
avoids Eloquent hydration and loads commit + image data using set-based SQL.
This PR keeps that query unchanged and evaluates **only** whether replacing
the Blade rendering layer with a direct XML serializer adds measurable value.

The synthetic dataset contains **10,000 commit URLs, with 2,500 image URLs**.
The benchmark warms both implementations, checks canonical XML equivalence,
and performs **30 alternating measurements** of each renderer on the same
GitHub Actions runner. Units are wall time in milliseconds.

| GitHub Actions run | Blade p50 | Direct XML p50 | Blade p95 | Direct XML p95 |
| --- | ---: | ---: | ---: | ---: |
| [Run 39575154091](https://github.com/Xauele/dev-null.sh/actions/runs/39575154091) | 36.393 | 13.022 | 37.215 | 13.601 |
| [Run 39575841934](https://github.com/Xauele/dev-null.sh/actions/runs/39575841934) | 40.171 | 13.076 | 42.053 | 13.335 |
| [Run 39926403949](https://github.com/Xauele/dev-null.sh/actions/runs/39926403949) | 33.736 | 11.557 | 34.852 | 11.649 |

The observed **render-only median ratio** is about **2.8x to 3.1x**.
This is a microbenchmark, **not an endpoint speedup**: it excludes SQL,
route generation, middleware and HTTP delivery, which may dominate total
latency. Results are runner- and fixture-specific, not production SLO claims.

## Correctness and integration

Canonical XML structures matched in all three benchmark runs. The new unit tests
also validate escaping, image nodes, empty sitemaps and 10,000 URL entries.
The existing feature tests cover image order and ETag.

The refreshed validation on upstream production `053bb309` passes all four
CI jobs (lint, types, type coverage and tests). The PHP suite reports 3,977
passed tests, 21,982 assertions and 100% coverage. The fork harness sets
`GITHUB_REPOSITORY=dev-null-sh/dev-null.sh` for existing upstream fixtures;
that environment override and the benchmark script are not part of this PR.

Earlier runs on the old setup failed and are retained in the links above;
only the refreshed run is evidence of passing integration tests.

The existing single-query sitemap SQL introduced upstream remains intact.
