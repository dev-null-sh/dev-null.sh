/// <reference types="node" />
import assert from 'node:assert/strict';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import {
    useListedLiveStreams,
    useLiveStream,
} from '../../resources/js/hooks/use-live-stream';
import { connectRealtime, pageReadAt } from '../../resources/js/lib/realtime';
import type { LiveStream } from '../../resources/js/types';
import { fakeConnection } from './FakeRealtime';

type Person = { id: string; live: LiveStream | null };

const endingIn = (url: string, milliseconds: number): LiveStream => ({
    url,
    thumbnail_url: `${url}/thumbnail.jpg`,
    ends_at: new Date(Date.now() + milliseconds).toISOString(),
});

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

let shown: Record<string, string | null> = {};
let listed: { id: string; live: string | null }[] = [];
const seeShown = (id: string, url: string | null) => {
    shown[id] = url;
};
const seeListed = (next: { id: string; live: string | null }[]) => {
    listed = next;
};
let reads: number;
let root: ReturnType<typeof createRoot>;

function Shown({ person }: { person: Person }) {
    seeShown(person.id, useLiveStream(person)?.url ?? null);

    return null;
}

function Page({
    people,
    aside,
    url,
}: {
    people: Person[];
    aside: Person[];
    url: string;
}) {
    seeListed(
        useListedLiveStreams(aside, url).map(({ user, live }) => ({
            id: user.id,
            live: live?.url ?? null,
        })),
    );

    return (
        <>
            {people.map((person) => (
                <Shown key={person.id} person={person} />
            ))}
        </>
    );
}

beforeEach(() => {
    vi.stubGlobal('window', globalThis);
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.useFakeTimers({
        toFake: [
            'setTimeout',
            'clearTimeout',
            'setInterval',
            'clearInterval',
            'Date',
        ],
    });
    root = createRoot(container());
    shown = {};
    listed = [];
    reads = 0;
    vi.stubGlobal('fetch', async () => {
        reads++;

        return new Response('', { status: 503 });
    });
    connectRealtime(null);
    pageReadAt(Date.now() - 1_000);
});

afterEach(() => {
    act(() => root.unmount());
    connectRealtime(null);
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

test('each stream a page shows runs out when its response said, though nothing can be heard and every read fails', async () => {
    const author = {
        id: 'author',
        live: endingIn('https://twitch.tv/author', 60_000),
    };
    const replier = {
        id: 'replier',
        live: endingIn('https://twitch.tv/replier', 600_000),
    };

    act(() =>
        root.render(
            <Page
                people={[author, replier]}
                aside={[author, replier]}
                url="/log/latest"
            />,
        ),
    );
    await act(() => vi.advanceTimersByTimeAsync(90_000));

    assert.deepEqual(shown, {
        author: null,
        replier: 'https://twitch.tv/replier',
    });
    assert.deepEqual(listed, [
        { id: 'author', live: null },
        { id: 'replier', live: 'https://twitch.tv/replier' },
    ]);

    await act(() => vi.advanceTimersByTimeAsync(600_000));

    assert.deepEqual(shown, { author: null, replier: null });
    assert.equal(reads, 0);
});

test('a stream extended or restarted at the same link shows until the end the next page read, not the one an earlier page said', async () => {
    const before = {
        id: 'streamer',
        live: endingIn('https://twitch.tv/streamer', 10_000),
    };

    act(() =>
        root.render(
            <Page people={[before]} aside={[before]} url="/log/latest" />,
        ),
    );
    await act(() => vi.advanceTimersByTimeAsync(30_000));
    assert.deepEqual(shown, { streamer: null });

    const extended = {
        id: 'streamer',
        live: endingIn('https://twitch.tv/streamer', 3_600_000),
    };
    pageReadAt(Date.now());
    act(() =>
        root.render(
            <Page people={[extended]} aside={[extended]} url="/@streamer" />,
        ),
    );
    await act(() => vi.advanceTimersByTimeAsync(1_800_000));

    assert.deepEqual(shown, { streamer: 'https://twitch.tv/streamer' });
    assert.deepEqual(listed, [
        { id: 'streamer', live: 'https://twitch.tv/streamer' },
    ]);

    await act(() => vi.advanceTimersByTimeAsync(1_800_000));

    assert.deepEqual(shown, { streamer: null });
    assert.deepEqual(listed, [{ id: 'streamer', live: null }]);
    assert.equal(reads, 0);
});

test('however many other people are live, the person shown stops showing live when their stream runs out', async () => {
    const others = Array.from({ length: 101 }, (_, index) => ({
        id: `other-${index}`,
        live: endingIn(`https://twitch.tv/other-${index}`, 30_000),
    }));
    const streamer = {
        id: 'streamer',
        live: endingIn('https://twitch.tv/streamer', 60_000),
    };

    act(() =>
        root.render(
            <Page people={[...others, streamer]} aside={[]} url="/@streamer" />,
        ),
    );
    await act(() => vi.advanceTimersByTimeAsync(45_000));
    assert.equal(shown.streamer, 'https://twitch.tv/streamer');

    await act(() => vi.advanceTimersByTimeAsync(30_000));

    assert.equal(shown.streamer, null);
    assert.equal(reads, 0);
});

test('joining again while reads fail asks once a join and leaves nothing waiting to ask again', async () => {
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    fake.setStatus('connected');
    const streamer = {
        id: 'streamer',
        live: endingIn('https://twitch.tv/streamer', 60_000),
    };

    act(() =>
        root.render(<Page people={[streamer]} aside={[]} url="/@streamer" />),
    );

    for (let join = 0; join < 10; join++) {
        fake.joined.get('profiles.streamer')?.join();
        await act(() => vi.advanceTimersByTimeAsync(100));
    }

    await act(() => vi.advanceTimersByTimeAsync(2_000_000));

    assert.equal(reads, 10);
    assert.equal(shown.streamer, null);
});
