import assert from 'node:assert/strict';
import { performance } from 'node:perf_hooks';
import { pathToFileURL } from 'node:url';
import * as current from '../../resources/js/lib/utils.ts';

// Optional original module, saved inside node_modules so its package imports resolve.
const baselinePath = process.argv[2];
if (!baselinePath) {
    throw new Error(
        'Pass the path to the original compiled utils module; see PERFORMANCE.md.',
    );
}
const baseline = (await import(
    pathToFileURL(baselinePath).href
)) as typeof current;
const now = new Date('2026-10-27T12:00:00Z');
const dates = Array.from({ length: 100 }, (_, i) =>
    new Date(now.getTime() - i * 86_400_000).toISOString(),
);
const cases = {
    'feed (100 cards)': (lib: typeof current) =>
        dates.map((date) => [
            lib.formatRelativeTime(date, now),
            lib.formatFullDateTime(date),
        ]),
    'chat (100 messages)': (lib: typeof current) =>
        dates.map((date) => lib.formatClockTime(date)),
    'profile clocks (100)': (lib: typeof current) =>
        dates.map((date) => [
            lib.formatZonedTime(date, 'Europe/Lisbon'),
            lib.formatUtcOffset(new Date(date), 'Europe/Lisbon'),
        ]),
};

for (const lib of [baseline, current]) {
    lib.setReaderClock({ timeZone: 'America/New_York', hourCycle: 'h12' });
}

const rows = [];
for (const [name, run] of Object.entries(cases)) {
    assert.deepEqual(run(current), run(baseline), `${name}: output changed`);
    for (let i = 0; i < 10; i++) {
        run(baseline);
        run(current);
    }
    const samples = { before: [] as number[], after: [] as number[] };
    for (let i = 0; i < 31; i++) {
        // Alternate order to reduce order/thermal bias. Each sample is one 100-item batch.
        for (const key of i % 2
            ? (['after', 'before'] as const)
            : (['before', 'after'] as const)) {
            const start = performance.now();
            run(key === 'before' ? baseline : current);
            samples[key].push(performance.now() - start);
        }
    }
    const median = (values: number[]) => values.sort((a, b) => a - b)[15]!;
    const before = median(samples.before),
        after = median(samples.after);
    rows.push({
        workload: name,
        before_ms: +before.toFixed(3),
        after_ms: +after.toFixed(3),
        speedup: +(before / after).toFixed(2),
        reduction_percent: +((1 - after / before) * 100).toFixed(1),
    });
}
console.log(
    JSON.stringify(
        {
            runtime: process.version,
            platform: process.platform,
            arch: process.arch,
            samples: 31,
            results: rows,
        },
        null,
        2,
    ),
);
baseline.setReaderClock(null);
current.setReaderClock(null);
