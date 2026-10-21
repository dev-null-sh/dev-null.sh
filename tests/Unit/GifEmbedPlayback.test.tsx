/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ImgHTMLAttributes, ReactElement, ReactNode } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { afterAll, afterEach, test, vi } from 'vite-plus/test';
import type { Embed, LinkPreview } from '../../resources/js/types/commit';

const playback = {
    isPlaying: true,
    hasFailed: false,
    canRetry: false,
    retryCount: 0,
    onError: () => {},
    onToggle: () => {},
    onRetry: vi.fn(),
};

let states: unknown[] = [];
let stateIndex = 0;

vi.doMock('react', () => ({
    ...React,
    useRef: (initial: unknown) => ({ current: initial }),
    useState: (initial: unknown) => {
        const index = stateIndex++;
        states[index] ??= typeof initial === 'function' ? initial() : initial;

        return [
            states[index],
            (value: unknown) => {
                states[index] =
                    typeof value === 'function' ? value(states[index]) : value;
            },
        ];
    },
}));

vi.doMock('../../resources/js/hooks/use-gif-playback', () => ({
    useGifPlayback: () => playback,
}));

const { GifEmbed } = await import('../../resources/js/components/gif-embed');

afterAll(() => vi.doUnmock('../../resources/js/hooks/use-gif-playback'));
afterEach(() => {
    playback.isPlaying = true;
    playback.hasFailed = false;
    playback.canRetry = false;
    playback.retryCount = 0;
    playback.onRetry.mockClear();
    states = [];
    stateIndex = 0;
});

const preview: LinkPreview = {
    url: 'https://giphy.com/gifs/cat',
    title: null,
    description: null,
    image_url: null,
    site_name: null,
    is_pending: false,
};

const embed: Embed = {
    provider: 'GIPHY',
    kind: 'gif',
    src: 'https://media.giphy.com/media/cat/giphy.gif',
    poster: 'https://media.giphy.com/media/cat/giphy_s.gif',
    aspect_ratio: null,
    height: null,
};

function render(current: Embed = embed): string {
    startRender();

    return renderToStaticMarkup(<GifEmbed preview={preview} embed={current} />);
}

function startRender() {
    stateIndex = 0;
}

function startPlaybackSession() {
    states = [];
    startRender();
}

function animation(
    current: Embed = embed,
): ReactElement<ImgHTMLAttributes<HTMLImageElement>> {
    const content = mediaContent(current);
    const image = React.Children.toArray(
        content.props.children,
    )[1] as ReactElement<{
        preview: LinkPreview;
        embed: Embed;
        playback: typeof playback;
    }>;

    return (
        image.type as (
            props: typeof image.props,
        ) => ReactElement<ImgHTMLAttributes<HTMLImageElement>>
    )(image.props);
}

function frame(current: Embed = embed): ReactElement<{ children: ReactNode }> {
    startRender();
    const card = GifEmbed({ preview, embed: current }) as ReactElement<{
        children: ReactNode;
    }>;
    return React.Children.toArray(card.props.children)[0] as ReactElement<{
        children: ReactNode;
    }>;
}

function mediaContent(
    current: Embed = embed,
): ReactElement<{ children: ReactNode }> {
    const media = React.Children.toArray(
        frame(current).props.children,
    )[0] as ReactElement<{
        preview: LinkPreview;
        embed: Embed;
        playback: typeof playback;
    }>;

    return (
        media.type as (props: typeof media.props) => ReactElement<{
            children: ReactNode;
        }>
    )(media.props);
}

test('keeps the static poster underneath an animation that has not loaded', () => {
    const html = render();

    assert.ok(html.includes('data-test="gif-poster"'));
    assert.match(
        html,
        /<img[^>]*class="[^"]*invisible[^>]*data-test="gif-animation"/,
    );
});

test('keeps the direct GIF placeholder while its animation loads', () => {
    const html = render({ ...embed, poster: null });

    assert.match(html, /<span class="absolute[^>]*>GIF<\/span>/);
    assert.match(
        html,
        /<img[^>]*class="[^"]*invisible[^>]*data-test="gif-animation"/,
    );
});

test('shows a loaded animation and starts a fresh playback session hidden', () => {
    const loading = animation();

    assert.match(String(loading.props.className), /invisible/);
    loading.props.onLoad?.({} as React.SyntheticEvent<HTMLImageElement>);

    const loaded = animation();

    assert.doesNotMatch(String(loaded.props.className), /invisible/);

    startPlaybackSession();

    const restarted = animation({ ...embed, src: `${embed.src}?restart=1` });

    assert.match(String(restarted.props.className), /invisible/);
});

test('Stop and offscreen states unload the animation and retain the poster', () => {
    playback.isPlaying = false;
    const html = render();

    assert.ok(html.includes('data-test="gif-poster"'));
    assert.ok(!html.includes('data-test="gif-animation"'));
    assert.ok(html.includes('aria-label="Play GIF"'));
});

test('a failed animation retains the card and reports its failure', () => {
    playback.isPlaying = false;
    playback.hasFailed = true;
    const html = render();

    assert.ok(html.includes('aspect-square'));
    assert.ok(html.includes('GIF could not load'));
    assert.ok(!html.includes('<img'));
    assert.ok(!html.includes('<button'));
});

test('a failed GIF offers a visible retry control while attempts remain', () => {
    playback.isPlaying = false;
    playback.hasFailed = true;
    playback.canRetry = true;
    const html = render();

    assert.ok(html.includes('GIF could not load'));
    assert.ok(html.includes('aria-label="Retry GIF"'));
    assert.ok(!html.includes('opacity-0'));
    assert.ok(!html.includes('data-test="gif-animation"'));
});

test('the retry control invokes retry rather than toggling playback', () => {
    playback.isPlaying = false;
    playback.hasFailed = true;
    playback.canRetry = true;
    const control = React.Children.toArray(
        frame().props.children,
    )[1] as ReactElement<{
        embed: Embed;
        playback: typeof playback;
        isStatic: boolean;
    }>;
    const button = (
        control.type as (props: typeof control.props) => ReactElement<{
            onClick: () => void;
        }>
    )(control.props);
    button.props.onClick();

    assert.equal(playback.onRetry.mock.calls.length, 1);
});

test('retry keeps the verified animation and poster URLs unchanged', () => {
    playback.retryCount = 1;
    const html = render();

    assert.ok(html.includes(`src="${embed.src}"`));
    assert.ok(html.includes(`src="${embed.poster}"`));
});

test('poster failures survive Stop and visibility changes but reset on manual retry or new media', () => {
    playback.isPlaying = false;
    const fallback = React.Children.toArray(
        mediaContent().props.children,
    )[0] as ReactElement<{
        preview: LinkPreview;
        embed: Embed;
        playback: typeof playback;
    }>;
    const poster = (
        fallback.type as (
            props: typeof fallback.props,
        ) => ReactElement<ImgHTMLAttributes<HTMLImageElement>>
    )(fallback.props);
    poster.props.onError?.({} as React.SyntheticEvent<HTMLImageElement>);

    assert.ok(!render().includes('data-test="gif-poster"'));
    playback.isPlaying = true;
    assert.ok(!render().includes('data-test="gif-poster"'));
    const unchanged = React.Children.toArray(
        mediaContent().props.children,
    )[0] as ReactElement;
    assert.equal(unchanged.key, fallback.key);

    playback.retryCount = 1;
    const retried = React.Children.toArray(
        mediaContent().props.children,
    )[0] as ReactElement;
    assert.notEqual(retried.key, fallback.key);
    const changed = React.Children.toArray(
        mediaContent({
            ...embed,
            poster: 'https://media.giphy.com/media/dog/giphy_s.gif',
        }).props.children,
    )[0] as ReactElement;
    assert.notEqual(changed.key, retried.key);
});
