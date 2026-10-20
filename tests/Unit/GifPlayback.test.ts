/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { canLoadGifPoster, isGifPlaying, isGifUrl } from '../../resources/js/lib/gifs.ts';

const validGifUrls = [
    'https://example.com/animation.gif',
    'https://example.com/animation.GIF?token=abc#frame',
    'https://media2.giphy.com/media/cat/giphy.gif',
    'https://example.com:443/cat.gif',
];

for (const url of validGifUrls) {
    void test(`recognizes a direct HTTPS GIF link: ${url}`, () => {
        assert.equal(isGifUrl(url), true);
        assert.equal(isGifPlaying({ ...playback, source: url }), true);
    });
}

const invalidGifUrls = [
    'not a URL',
    'http://example.com/cat.gif',
    'data:image/gif;base64,R0lGODlh',
    'javascript:alert(1)',
    'https://example.com/page?image=cat.gif',
    'https://example.com/cat.gif/page',
    'https://example.com/cat.gif.html',
    'https://user:secret@example.com/cat.gif',
    'https://user@example.com/cat.gif',
    'https://:secret@example.com/cat.gif',
    'https://example.com:8443/cat.gif',
];

for (const url of invalidGifUrls) {
    void test(`does not treat an unsupported link as a GIF: ${url}`, () => {
        assert.equal(isGifUrl(url), false);
        assert.equal(isGifPlaying({ ...playback, source: url }), false);
    });
}

const playback = {
    source: 'https://media.giphy.com/media/cat/giphy.gif',
    playback: 'auto' as const,
    isVisible: true,
    reducedMotion: false,
    isStatic: false,
    hasFailed: false,
};

void test('autoplays visible GIFs and suspends them outside the viewport', () => {
    assert.equal(isGifPlaying(playback), true);
    assert.equal(isGifPlaying({ ...playback, isVisible: false }), false);
});

void test('reduced motion requires an explicit Play action', () => {
    assert.equal(isGifPlaying({ ...playback, reducedMotion: true }), false);
    assert.equal(
        isGifPlaying({ ...playback, reducedMotion: true, playback: 'play' }),
        true,
    );
});

void test('Stop survives leaving and reentering the viewport', () => {
    assert.equal(isGifPlaying({ ...playback, playback: 'stop' }), false);
    assert.equal(
        isGifPlaying({ ...playback, playback: 'stop', isVisible: false }),
        false,
    );
    assert.equal(isGifPlaying({ ...playback, playback: 'stop' }), false);
    assert.equal(isGifPlaying({ ...playback, playback: 'play' }), true);
});

void test('static cards and failed GIFs never animate, even after Play', () => {
    assert.equal(isGifPlaying({ ...playback, isStatic: true }), false);
    assert.equal(
        isGifPlaying({ ...playback, playback: 'play', isStatic: true }),
        false,
    );
    assert.equal(isGifPlaying({ ...playback, hasFailed: true }), false);
    assert.equal(
        isGifPlaying({ ...playback, playback: 'play', hasFailed: true }),
        false,
    );
});

void test('only canonical Giphy media can supply a static poster', () => {
    assert.equal(canLoadGifPoster('https://media.giphy.com/media/cat/giphy.gif'), true);
    for (const source of [
        'https://example.com/cat.gif',
        'https://media.giphy.com.attacker.example/cat.gif',
        'https://attacker.giphy.com/cat.gif',
        'http://media.giphy.com/media/cat/giphy.gif',
        'https://user:secret@media.giphy.com/media/cat/giphy.gif',
        'https://user@media.giphy.com/media/cat/giphy.gif',
        'https://:secret@media.giphy.com/media/cat/giphy.gif',
        'https://media.giphy.com:8443/media/cat/giphy.gif',
        'not a URL',
    ]) {
        assert.equal(canLoadGifPoster(source), false);
    }
});

void test('external GIFs autoplay while visible and respect playback preferences', () => {
    const external = { ...playback, source: 'https://example.com/cat.gif' };
    assert.equal(isGifPlaying(external), true);
    assert.equal(isGifPlaying({ ...external, isVisible: false }), false);
    assert.equal(isGifPlaying({ ...external, reducedMotion: true }), false);
    assert.equal(isGifPlaying({ ...external, reducedMotion: true, playback: 'play' }), true);
    assert.equal(isGifPlaying({ ...external, playback: 'play' }), true);
    assert.equal(isGifPlaying({ ...external, playback: 'stop' }), false);
    assert.equal(isGifPlaying({ ...external, playback: 'play', isVisible: false }), false);
});
