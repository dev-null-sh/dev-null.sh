/// <reference types="node" />
import assert from 'node:assert/strict';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { useListedLiveStreams } from '../../resources/js/hooks/use-live-stream';
import { rememberLiveDeadlines } from '../../resources/js/lib/live-streams';
import { connectRealtime } from '../../resources/js/lib/realtime';
import type { LiveStream } from '../../resources/js/types';
import { fakeConnection } from './FakeRealtime';

vi.stubGlobal('window', globalThis);
vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);

type Person = { id: string; live: LiveStream | null };

const stream = (url: string): LiveStream =>
    ({ url, thumbnail_url: null, ends_at: null }) as unknown as LiveStream;

function container(): Element {
    const node = {
        nodeType: 1,
        nodeName: 'DIV',
        tagName: 'DIV',
        addEventListener: () => {},
        removeEventListener: () => {},
    };

    return {
        ...node,
        ownerDocument: {
            ...node,
            defaultView: {
                document: { activeElement: null },
                HTMLIFrameElement: class {},
            },
        },
    } as unknown as Element;
}

let fake: ReturnType<typeof fakeConnection>;
let listed: { id: string; live: string | null }[] = [];

function List({ people, url }: { people: Person[]; url: string }) {
    listed = useListedLiveStreams(people, url).map(({ user, live }) => ({
        id: user.id,
        live: live?.url ?? null,
    }));

    return null;
}

beforeEach(() => {
    fake = fakeConnection();
    connectRealtime(fake.connection);
});

afterEach(() => {
    connectRealtime(null);
    rememberLiveDeadlines({});
    vi.unstubAllGlobals();
    vi.stubGlobal('window', globalThis);
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.useRealTimers();
});

test('the live list keeps who it showed while the page is read, and changes on the next page', () => {
    const root = createRoot(container());
    const ines = { id: 'ines', live: stream('https://twitch.tv/ines') };
    const theo = { id: 'theo', live: stream('https://twitch.tv/theo') };

    act(() => root.render(<List people={[ines]} url="/log/latest" />));
    assert.deepEqual(listed, [{ id: 'ines', live: 'https://twitch.tv/ines' }]);

    // Someone followed going live is heard of, and their stream ending too, while the page is still open.
    act(() => root.render(<List people={[ines, theo]} url="/log/latest" />));
    act(() =>
        fake.joined.get('profiles.ines')?.emit('profile', { live: null }),
    );
    act(() => root.render(<List people={[theo]} url="/log/latest" />));
    assert.deepEqual(listed, [{ id: 'ines', live: null }]);

    act(() => root.render(<List people={[theo]} url="/@theo" />));
    assert.deepEqual(listed, [{ id: 'theo', live: 'https://twitch.tv/theo' }]);

    act(() => root.unmount());
});

test('a stream the page handed without its end leaves the list when the page said it runs out, though nothing can be read', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval', 'Date'] });
    connectRealtime(null);
    let reads = 0;
    vi.stubGlobal('fetch', async () => {
        reads++;

        return new Response('', { status: 503 });
    });
    rememberLiveDeadlines({ maya: new Date(Date.now() + 60_000).toISOString() });
    const handed = { url: 'https://twitch.tv/maya', thumbnail_url: 'thumbnail' } as LiveStream;
    const root = createRoot(container());

    act(() => root.render(<List people={[{ id: 'maya', live: handed }]} url="/log/latest" />));
    assert.deepEqual(listed, [{ id: 'maya', live: 'https://twitch.tv/maya' }]);

    await act(() => vi.advanceTimersByTimeAsync(90_000));

    assert.deepEqual(listed, [{ id: 'maya', live: null }]);
    assert.equal(reads, 0);
    act(() => root.unmount());
});

