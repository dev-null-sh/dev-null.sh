/// <reference types="node" />
import assert from 'node:assert/strict';
import type { ReactNode } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { test, vi } from 'vite-plus/test';
import { CommitFeed } from '../../resources/js/components/commit-feed';
import { CommitImages } from '../../resources/js/components/commit-images';
import type { Commit } from '../../resources/js/types/commit';

vi.mock('@/lib/commit-photo', () => ({
    commitPhotoUrl: () => '/photo',
    openCommitPhoto: vi.fn(),
}));

const images = [
    {
        id: 'first',
        url: 'https://example.com/first.webp',
        description: 'First photo',
        width: 1200,
        height: 600,
    },
    {
        id: 'second',
        url: 'https://example.com/second.webp',
        description: 'Second photo',
        width: 600,
        height: 900,
    },
];

test('makes the first priority photo discoverable and visible in server HTML without JavaScript', () => {
    const markup = renderToStaticMarkup(
        <CommitImages images={images} priority />,
    );
    const tags = markup.match(/<img\b[^>]*>/g)!;
    assert.equal(tags.length, 2);
    assert.match(tags[0], /loading="eager"/);
    assert.match(tags[0], /fetchPriority="high"/i);
    assert.doesNotMatch(tags[0], /opacity-0/);
    assert.match(tags[0], /width="1200"/);
    assert.match(tags[0], /height="600"/);
    assert.match(tags[0], /alt="First photo"/);
    assert.match(tags[1], /loading="lazy"/);
    assert.doesNotMatch(tags[1], /fetchPriority="high"/i);
    assert.equal(
        (markup.match(/data-test="commit-image-loading"/g) ?? []).length,
        1,
    );
});

test('keeps ordinary photo grids lazy with their loading placeholders', () => {
    const markup = renderToStaticMarkup(<CommitImages images={images} />);
    const tags = markup.match(/<img\b[^>]*>/g)!;
    assert.ok(tags.every((tag) => tag.includes('loading="lazy"')));
    assert.ok(tags.every((tag) => tag.includes('opacity-0')));
    assert.doesNotMatch(markup, /fetchPriority="high"/i);
    assert.equal(
        (markup.match(/data-test="commit-image-loading"/g) ?? []).length,
        2,
    );
});

const page = vi.hoisted(() => ({
    component: 'log/show',
    props: { dimsLanes: false },
}));
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    router: { on: vi.fn(() => () => {}) },
    InfiniteScroll: ({ children }: { children: ReactNode }) => (
        <div>{children}</div>
    ),
}));
vi.mock('@/hooks/use-now', () => ({
    useNow: () => new Date('2026-10-27T12:00:00Z'),
}));
vi.mock('@/components/commit-card', () => ({
    CommitCard: ({
        commit,
        prioritizeImage,
    }: {
        commit: Commit;
        prioritizeImage?: boolean;
    }) => (
        <div
            data-card={commit.id}
            data-priority={prioritizeImage ? 'high' : 'lazy'}
        />
    ),
}));
vi.mock('@/components/graph-canvas', () => ({
    GraphCanvas: ({ children }: { children: ReactNode }) => (
        <div>{children}</div>
    ),
}));
vi.mock('@/components/graph-rail', () => ({
    GraphRail: () => null,
    RailSegment: () => null,
}));

const card = (id: string) =>
    ({ id, created_at: '2026-10-27T10:00:00Z', user: { lane: 0 } }) as Commit;
function feedMarkup(pinnedCommit: Commit | null = null) {
    return renderToStaticMarkup(
        <CommitFeed
            commits={
                { data: [card('first'), card('second')] } as Parameters<
                    typeof CommitFeed
                >[0]['commits']
            }
            empty={null}
            end="End"
            pinnedCommit={pinnedCommit}
        />,
    );
}

test('promotes one first log card, or the pinned card, without promoting the rest', () => {
    page.component = 'log/show';
    let markup = feedMarkup();
    assert.match(markup, /data-card="first" data-priority="high"/);
    assert.match(markup, /data-card="second" data-priority="lazy"/);
    markup = feedMarkup(card('pinned'));
    assert.match(markup, /data-card="pinned" data-priority="high"/);
    assert.match(markup, /data-card="first" data-priority="lazy"/);
    assert.equal((markup.match(/data-priority="high"/g) ?? []).length, 1);
});

test('keeps feeds below profile headers lazy', () => {
    page.component = 'user/show';
    Object.assign(page.props, { user: { id: 'profile' }, tab: 'commits' });
    assert.doesNotMatch(feedMarkup(), /data-priority="high"/);
});
