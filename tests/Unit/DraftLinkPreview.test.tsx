/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterEach, test, vi } from 'vite-plus/test';
import type { LinkPreview } from '../../resources/js/types/commit';

let result: {
    preview: LinkPreview | null;
    isLoading: boolean;
} = { preview: null, isLoading: true };

vi.doMock('../../resources/js/hooks/use-draft-link-preview', () => ({
    useDraftLinkPreview: () => result,
}));

const { DraftLinkPreview } =
    await import('../../resources/js/components/draft-link-preview');

const directGif = 'https://images.example.test/cat.gif';

function render(url: string | null): string {
    return renderToStaticMarkup(<DraftLinkPreview url={url} />);
}

afterEach(() => {
    result = { preview: null, isLoading: true };
});

test('renders nothing without a selected link card', () => {
    assert.equal(render(null), '');
});

test('keeps an unverified GIF pending without requesting media or exposing Play', () => {
    const markup = render(directGif);

    assert.ok(markup.includes('data-test="gif-embed"'));
    assert.ok(markup.includes('aspect-square'));
    assert.ok(markup.includes('Loading GIF'));
    assert.ok(!markup.includes('<img'));
    assert.ok(!markup.includes('Play GIF'));
});

test('keeps a pending www Giphy share link in the GIF card', () => {
    const markup = render('https://www.giphy.com/gifs/JIX9t2j0ZTN9S');

    assert.ok(markup.includes('data-test="gif-embed"'));
    assert.ok(markup.includes('aspect-square'));
    assert.ok(markup.includes('Loading GIF'));
    assert.ok(!markup.includes('<img'));
});

test('renders a pending backend preview through the shared preview poller', async () => {
    let refreshUrl: string | null = null;

    vi.resetModules();
    vi.doMock('../../resources/js/hooks/use-draft-link-preview', () => ({
        useDraftLinkPreview: () => ({
            preview: pendingPreview('https://example.test/article'),
            isLoading: false,
        }),
    }));
    vi.doMock('../../resources/js/components/link-preview', () => ({
        LinkPreview: ({ refreshUrl: url }: { refreshUrl: string }) => {
            refreshUrl = url;

            return <div data-test="shared-link-preview" />;
        },
    }));

    const { DraftLinkPreview: PendingDraftLinkPreview } =
        await import('../../resources/js/components/draft-link-preview');
    const markup = renderToStaticMarkup(
        <PendingDraftLinkPreview url="https://example.test/article" />,
    );

    assert.ok(markup.includes('data-test="shared-link-preview"'));
    assert.equal(
        refreshUrl,
        '/draft-link-preview?url=https%3A%2F%2Fexample.test%2Farticle',
    );

    vi.doUnmock('../../resources/js/components/link-preview');
    vi.resetModules();
});

test('renders verified Giphy and direct GIFs through the shared GIF component', () => {
    for (const preview of [
        verifiedGif('https://giphy.com/gifs/JIX9t2j0ZTN9S', {
            provider: 'GIPHY',
            src: 'https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy.gif',
            poster: 'https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy_s.gif',
        }),
        verifiedGif(directGif, {
            provider: 'GIF',
            src: directGif,
            poster: null,
        }),
    ]) {
        result = { preview, isLoading: false };
        const markup = render(preview.url);

        assert.ok(markup.includes('data-test="gif-embed"'));
        assert.ok(markup.includes(`href="${preview.url}"`));
    }
});

test('shows loading and unavailable states for non-GIF links', () => {
    const url = 'https://example.test/article';

    assert.ok(render(url).includes('Loading link preview…'));

    result = { preview: null, isLoading: false };

    assert.ok(render(url).includes('Link preview unavailable'));
});

test('keeps an unavailable card when a completed draft has no metadata', () => {
    const url = 'https://example.test/unavailable';
    result = {
        preview: {
            ...pendingPreview(url),
            is_pending: false,
        },
        isLoading: false,
    };

    const markup = render(url);

    assert.ok(markup.includes('data-test="link-card-preview"'));
    assert.ok(markup.includes('Link preview unavailable'));
});

function verifiedGif(
    url: string,
    embed: Omit<
        NonNullable<LinkPreview['embed']>,
        'kind' | 'aspect_ratio' | 'height'
    >,
): LinkPreview {
    return {
        url,
        title: null,
        description: null,
        image_url: null,
        site_name: null,
        is_pending: false,
        embed: { kind: 'gif', aspect_ratio: null, height: null, ...embed },
    };
}

function pendingPreview(url: string): LinkPreview {
    return {
        url,
        title: null,
        description: null,
        image_url: null,
        site_name: null,
        is_pending: true,
        embed: null,
    };
}
