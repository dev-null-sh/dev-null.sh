import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { performance } from 'node:perf_hooks';
import { pathToFileURL } from 'node:url';
import type { GraphNode } from '../../resources/js/lib/graph.ts';
import type { Message } from '../../resources/js/types/index.ts';

const load = (version: string, name: string) =>
    import(
        pathToFileURL(
            resolve(`node_modules/.cache/performance/${version}-${name}.mjs`),
        ).href
    );
const before = {
    graph: (await load(
        'baseline',
        'graph',
    )) as typeof import('../../resources/js/lib/graph.ts'),
    chat: (await load(
        'baseline',
        'chat-scroll',
    )) as typeof import('../../resources/js/lib/chat-scroll.ts'),
    certificates: (await load(
        'baseline',
        'certificate-hashes',
    )) as typeof import('../../resources/js/lib/certificate-hashes.ts'),
    emoji: (await load(
        'baseline',
        'emoji',
    )) as typeof import('../../resources/js/lib/emoji.ts'),
};
const after = {
    graph: (await load('current', 'graph')) as typeof before.graph,
    chat: (await load('current', 'chat-scroll')) as typeof before.chat,
    certificates: (await load(
        'current',
        'certificate-hashes',
    )) as typeof before.certificates,
    emoji: (await load('current', 'emoji')) as typeof before.emoji,
};
const rows: object[] = [];
function measure(
    name: string,
    old: () => unknown,
    updated: () => unknown,
    iterations = 100,
) {
    assert.deepEqual(updated(), old(), name);
    for (let i = 0; i < 20; i++) {
        old();
        updated();
    }
    const samples = { before: [] as number[], after: [] as number[] };
    for (let sample = 0; sample < 31; sample++) {
        for (const key of sample % 2
            ? (['after', 'before'] as const)
            : (['before', 'after'] as const)) {
            const run = key === 'before' ? old : updated;
            const start = performance.now();
            for (let i = 0; i < iterations; i++) run();
            samples[key].push((performance.now() - start) / iterations);
        }
    }
    const median = (values: number[]) => values.sort((a, b) => a - b)[15]!;
    const original = median(samples.before),
        current = median(samples.after);
    rows.push({
        workload: name,
        before_ms: +original.toFixed(5),
        after_ms: +current.toFixed(5),
        reduction_percent: +((1 - current / original) * 100).toFixed(1),
    });
}
const node = (
    id: string,
    y: number,
    rest: Partial<GraphNode> = {},
): GraphNode => ({ id, x: 32, y, radius: 12, lane: 'blue', ...rest });
for (const count of [20, 100, 500, 2000]) {
    const nodes = [
        node('root', 0),
        ...Array.from({ length: count }, (_, i) =>
            node(`reply-${i}`, (i + 1) * 60, { parent: 'root', x: 96 }),
        ),
    ];
    measure(
        `graph ${count} replies`,
        () => before.graph.graphEdges(nodes),
        () => after.graph.graphEdges(nodes),
        count >= 500 ? 10 : 100,
    );
}
const trunk = Array.from({ length: 500 }, (_, i) =>
    node(`${i}`, i * 60, { after: `${i - 1}` }),
);
measure(
    'graph 500 trunk-only nodes',
    () => before.graph.graphEdges(trunk),
    () => after.graph.graphEdges(trunk),
);

// Seeded shuffled layouts, including ties, multiple parents and trunks still drawing.
let seed = 42;
const random = () =>
    (seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0) / 2 ** 32;
for (let sample = 0; sample < 300; sample++) {
    const nodes = Array.from({ length: 100 }, (_, i) =>
        node(`${i}`, Math.floor(random() * 20) * 10, {
            x: Math.floor(random() * 4) * 32,
            parent:
                random() < 0.8 ? `${Math.floor(random() * 110)}` : undefined,
            after: `${Math.floor(random() * 110)}`,
            dashed: random() < 0.5,
            dimmed: random() < 0.5,
        }),
    );
    const drawing = new Set(
        nodes.filter(() => random() < 0.5).map((n) => `${n.after}->${n.id}`),
    );
    assert.deepEqual(
        after.graph.graphEdges(nodes, drawing),
        before.graph.graphEdges(nodes, drawing),
        `random graph ${sample}`,
    );
}

const originalCSS = globalThis.CSS;
globalThis.CSS = { escape: (value: string) => value } as typeof CSS;
const scroller = {
    querySelector: () => ({ getBoundingClientRect: () => ({ top: 700 }) }),
    getBoundingClientRect: () => ({ bottom: 600 }),
} as unknown as HTMLElement;
for (const count of [100, 1000, 10000]) {
    const messages = Array.from(
        { length: count },
        (_, i) =>
            ({
                client_id: `${i}`,
                event: i % 7 === 0 ? { type: 'joined' } : null,
            }) as Message,
    );
    measure(
        `chat scroll ${count} messages`,
        () => before.chat.isBehindLatest(scroller, messages),
        () => after.chat.isBehindLatest(scroller, messages),
        1000,
    );
}
globalThis.CSS = originalCSS;
for (const lines of [10, 100]) {
    const html =
        '<span class="hl-keyword">const</span> x = <span class="hl-number">42</span>;\n'.repeat(
            lines,
        );
    measure(
        `certificate scan ${lines} highlighted lines, no hash`,
        () => before.certificates.withCertificateHashes(html),
        () => after.certificates.withCertificateHashes(html),
    );
}
const fingerprint =
    'E7568FE6E12331F4FD7DE61DA4C7559D0F8EB192A218D4741A3BA75184333838';
measure(
    'certificate match present',
    () => before.certificates.withCertificateHashes(fingerprint),
    () => after.certificates.withCertificateHashes(fingerprint),
);
for (const query of ['', 'a', 'heart', 'no_such_shortcode']) {
    measure(
        `emoji search ${JSON.stringify(query)}`,
        () => before.emoji.searchEmoji(query),
        () => after.emoji.searchEmoji(query),
        30,
    );
}
const oldSearch = (await load(
    'baseline',
    'conversation-search',
)) as typeof import('../../resources/js/lib/conversation-search.ts');
const newSearch = (await load(
    'current',
    'conversation-search',
)) as typeof oldSearch;
for (const count of [100, 1000]) {
    const conversations = Array.from({ length: count }, (_, i) => ({
        id: `${i}`,
        partner: null,
        name: `team-${i}`,
    })) as import('../../resources/js/types/index.ts').ConversationSummary[];
    const found = conversations.slice(0, count / 2).map((c) => ({ ...c }));
    measure(
        `inbox search ${count} conversations`,
        () => oldSearch.conversationsMatching('team', conversations, found),
        () => newSearch.conversationsMatching('team', conversations, found),
    );
}
const shortcodes = Object.keys(
    JSON.parse(
        readFileSync('resources/js/lib/emoji-shortcodes.json', 'utf8'),
    ) as Record<string, string>,
);
const queries = new Set(
    shortcodes.flatMap((name) => [name.slice(0, 3), name.slice(-3), name]),
);
const uses = new Map([
    ['😍', 10],
    ['❤️', 5],
    ['👍', 12],
]);
for (const query of queries) {
    assert.deepEqual(
        after.emoji.searchEmoji(query, uses),
        before.emoji.searchEmoji(query, uses),
        `emoji ${query}`,
    );
}
console.log(
    JSON.stringify(
        {
            runtime: process.version,
            samples: 31,
            graph_parity_layouts: 300,
            emoji_parity_queries: queries.size,
            results: rows,
        },
        null,
        2,
    ),
);
