/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { Commit, CommitTranslation } from '../../resources/js/types';
import { hookHarness } from './HookHarness';

const harness = hookHarness();
let language = 'en';

vi.doMock('react', () => harness.react);
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({
        props: {
            now: Date.now(),
            auth: {
                user: { id: 'me' },
                automaticTranslation: true,
                translationLanguage: language,
            },
        },
    }),
    useHttp: () => ({
        transform: () => {},
        submit: async () => ({ translation: null }),
    }),
}));

const { useCommitTranslation } =
    await import('../../resources/js/components/commit-translation');

const translation = (code: string, body: string) =>
    ({
        language: code,
        available: true,
        source_name: 'Portuguese',
        translated: {
            body,
            body_segments: [{ type: 'text', text: body }],
            preview_segments: [],
        },
    }) as unknown as CommitTranslation;

const commit = {
    id: 'c',
    body: 'Olá',
    status: 'approved',
    body_segments: [{ type: 'text', text: 'Olá' }],
    preview_segments: [],
    translation: translation('en', 'Hello'),
} as unknown as Commit;

let reads: string[] = [];
let shown: ReturnType<typeof useCommitTranslation>;

function Probe() {
    shown = useCommitTranslation(commit);

    return null;
}

beforeEach(() => {
    vi.useFakeTimers();
    reads = [];
    language = 'en';
    vi.stubGlobal('fetch', async (input: string) => {
        const read =
            new URL(input, 'http://localhost').searchParams.get('language') ??
            '';
        reads.push(read);

        return Response.json({
            translation: translation(read, read === 'de' ? 'Hallo' : 'Hello'),
        });
    });
});

afterEach(() => {
    harness.unmount();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

async function settle() {
    for (let i = 0; i < 3; i++) {
        await vi.advanceTimersByTimeAsync(600);
        void harness.render(Probe, {});
    }
}

const text = () => (shown.segments as { text?: string }[])[0]?.text;

test('a translation language saved elsewhere reaches the translations already shown', async () => {
    void harness.render(Probe, {});
    await settle();
    assert.equal(text(), 'Hello');
    assert.deepEqual(reads, []);

    language = 'de';
    await settle();

    assert.deepEqual(reads, ['de']);
    assert.equal(text(), 'Hallo');
});

test('joining asks once what was translated among the commits shown, and reads again only those', async () => {
    const { fakeConnection } = await import('./FakeRealtime');
    const { connectRealtime } = await import('../../resources/js/lib/realtime');
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    const untranslated = (id: string) =>
        ({
            ...commit,
            id,
            translation: { ...translation('en', ''), translated: null },
        }) as unknown as Commit;
    const quiet = untranslated('quiet');
    const finished = untranslated('finished');
    const asked: string[] = [];
    vi.stubGlobal('fetch', async (input: string) => {
        const url = new URL(input, 'http://localhost');
        asked.push(url.pathname);

        return url.pathname === '/commits/changes'
            ? Response.json({
                  changes: {
                      quiet: {
                          changed: false,
                          replies: [],
                          truncated: false,
                          translated: false,
                      },
                      finished: {
                          changed: false,
                          replies: [],
                          truncated: false,
                          translated: true,
                      },
                  },
              })
            : Response.json({ translation: translation('en', 'Hello') });
    });
    function Both() {
        useCommitTranslation(quiet);

        return { finished: useCommitTranslation(finished) };
    }
    const both = Both as unknown as () => null;

    void harness.render(both, {});
    fake.setStatus('connected');
    fake.joined.get('commits.quiet')?.join();
    fake.joined.get('commits.finished')?.join();
    let rendered = harness.render(both, {});
    for (let i = 0; i < 3; i++) {
        await vi.advanceTimersByTimeAsync(600);
        rendered = harness.render(both, {});
    }
    const shownFinished = (rendered as unknown as ReturnType<typeof Both>)
        .finished;

    assert.deepEqual(asked, [
        '/commits/changes',
        '/commits/finished/translation',
    ]);
    assert.equal(
        (shownFinished.segments as { text?: string }[])[0]?.text,
        'Hello',
    );
    connectRealtime(null);
});

for (const failure of ['503', 'network'] as const) {
    test(`rejoining retries a completed translation whose read failed with ${failure}`, async () => {
        const { fakeConnection } = await import('./FakeRealtime');
        const { connectRealtime } =
            await import('../../resources/js/lib/realtime');
        const fake = fakeConnection();
        connectRealtime(fake.connection);
        const started = Date.now();
        const translatedAt = started + 1_000;
        const untranslated = {
            ...commit,
            translation: { ...translation('en', ''), translated: null },
        } as unknown as Commit;
        let gets = 0;
        vi.stubGlobal('fetch', async (input: string) => {
            const url = new URL(input, 'http://localhost');

            if (url.pathname === '/commits/changes') {
                return Response.json({
                    changes: {
                        c: {
                            changed: false,
                            replies: [],
                            truncated: false,
                            translated:
                                translatedAt >
                                Number(url.searchParams.get('since')),
                        },
                    },
                });
            }

            if (++gets === 1) {
                if (failure === 'network') {
                    throw new Error('Synthetic network failure');
                }

                return new Response(null, { status: 503 });
            }

            return Response.json({ translation: translation('en', 'Hello') });
        });
        function Recovering() {
            shown = useCommitTranslation(untranslated);

            return null;
        }

        try {
            void harness.render(Recovering, {});
            vi.setSystemTime(started + 20_000);
            fake.setStatus('connected');
            fake.joined.get('commits.c')?.join();
            await vi.advanceTimersByTimeAsync(600);
            void harness.render(Recovering, {});
            assert.equal(gets, 1);
            assert.equal(text(), 'Olá');

            vi.setSystemTime(started + 40_000);
            fake.setStatus('unavailable');
            fake.setStatus('connected');
            fake.joined.get('commits.c')?.join();
            await vi.advanceTimersByTimeAsync(600);
            void harness.render(Recovering, {});

            assert.equal(gets, 2);
            assert.equal(text(), 'Hello');
        } finally {
            connectRealtime(null);
        }
    });
}
