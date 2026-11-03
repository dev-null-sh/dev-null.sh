/// <reference types="node" />
import assert from 'node:assert/strict';
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import { useListedLiveStreams } from '../../resources/js/hooks/use-live-stream';
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
