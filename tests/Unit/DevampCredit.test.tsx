/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';

type Element = ReactElement<{ children?: ReactNode; href?: string; target?: string; rel?: string }>;

let playing: unknown = null;

vi.doMock('react', () => ({
    ...React,
    useSyncExternalStore: () => playing,
}));
vi.doMock('@/lib/devamp', () => ({ devampTrack: vi.fn(), subscribeToDevamp: vi.fn() }));

const { DevampCredit } = await import('../../resources/js/components/devamp-credit');

const track = {
    artist: 'Broey.',
    title: 'Paradise',
    duration: 143,
    url: 'https://api.audius.co/v1/tracks/emKAp/stream?app_name=devnull',
    link: 'https://audius.co/broeybeats/paradise',
};

function text(node: ReactNode): string {
    if (typeof node === 'string') {
        return node;
    }

    return React.Children.toArray((node as Element).props.children).map(text).join('');
}

function links(node: ReactNode): Element[] {
    if (typeof node !== 'object' || node === null) {
        return [];
    }

    const element = node as Element;

    return [
        ...(element.type === 'a' ? [element] : []),
        ...React.Children.toArray(element.props.children).flatMap(links),
    ];
}

test('shows nothing while the open player has no Audius track loaded', () => {
    playing = null;

    assert.equal(DevampCredit(), null);
});

test('credits the playing track, its artist and the Open Music License', () => {
    playing = track;
    const credit = DevampCredit() as Element;

    assert.deepEqual(React.Children.toArray(credit.props.children).map(text), [
        'Paradise',
        '© Broey.,',
        'from Audius under the Open Music License',
    ]);
    assert.deepEqual(
        links(credit).map(({ props }) => [props.href, props.target, props.rel]),
        [
            ['https://audius.co/broeybeats/paradise', '_blank', 'noopener noreferrer'],
            ['https://openaudiofoundation.org/open-music-license.pdf', '_blank', 'noopener noreferrer'],
        ],
    );
});
