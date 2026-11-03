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
    let finishedShown: ReturnType<typeof useCommitTranslation> | undefined;
    function Both() {
        useCommitTranslation(quiet);
        finishedShown = useCommitTranslation(finished);

        return null;
    }

    void harness.render(Both, {});
    fake.setStatus('connected');
    fake.joined.get('commits.quiet')?.join();
    fake.joined.get('commits.finished')?.join();
    for (let i = 0; i < 3; i++) {
        await vi.advanceTimersByTimeAsync(600);
        void harness.render(Both, {});
    }

    assert.deepEqual(asked, [
        '/commits/changes',
        '/commits/finished/translation',
    ]);
    assert.equal(
        (finishedShown?.segments as { text?: string }[])[0]?.text,
        'Hello',
    );
    connectRealtime(null);
});
