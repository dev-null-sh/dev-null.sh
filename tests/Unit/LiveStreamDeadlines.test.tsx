/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { LiveStream } from '../../resources/js/types/auth';
import { hookHarness } from './HookHarness';

const harness = hookHarness();

vi.doMock('react', () => harness.react);

const { connectRealtime, pageReadAt } = await import('../../resources/js/lib/realtime');
const { useLiveStream } = await import('../../resources/js/hooks/use-live-stream');
const { rememberLiveDeadlines } = await import('../../resources/js/lib/live-streams');

const handed = { url: 'https://www.twitch.tv/handed', thumbnail_url: 'thumbnail' } as LiveStream;
let reads: number;

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
    reads = 0;
    vi.stubGlobal('fetch', async () => {
        reads++;

        return new Response('', { status: 503 });
    });
    connectRealtime(null);
    pageReadAt(Date.now() - 1_000);
});

afterEach(() => {
    harness.unmount();
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

function Shown() {
    return { stream: useLiveStream({ id: 'streamer', live: handed }) };
}

const shown = () => (harness.render(Shown as unknown as () => null, {}) as unknown as ReturnType<typeof Shown>).stream;

test('a stream the page handed runs out when the page said, without asking, even when nothing can be read or heard', async () => {
    const endsAt = new Date(Date.now() + 60_000).toISOString();
    rememberLiveDeadlines({ streamer: endsAt });

    const stream = shown();
    await vi.advanceTimersByTimeAsync(60_000);

    assert.deepEqual(stream, { ...handed, ends_at: endsAt });
    assert.equal(reads, 0);
});

test('a stream the page handed without saying when it runs out is asked about', async () => {
    rememberLiveDeadlines({});

    assert.equal(shown()?.url, 'https://www.twitch.tv/handed');
    await vi.advanceTimersByTimeAsync(100);

    assert.equal(reads, 1);
});
