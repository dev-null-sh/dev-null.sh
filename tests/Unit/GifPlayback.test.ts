/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    canLoadGifPoster,
    canRetryGifLoad,
    failGifLoad,
    hasGifLoadFailed,
    isGifPlaying,
    isGifUrl,
    MAX_GIF_RETRIES,
    retryGifLoad,
    syncGifLoadRetryState,
    type GifLoadRetryState,
} from '../../resources/js/lib/gifs.ts';

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

void test('autoplays a server-verified GIF after an extensionless redirect', () => {
    const source = 'https://www.example.com/media?id=cat';

    assert.equal(isGifUrl(source), false);
    assert.equal(isGifPlaying({ ...playback, source }), true);
});

for (const source of [
    'not a URL',
    'http://example.com/media?id=cat',
    'https://user:secret@example.com/media?id=cat',
    'https://example.com:8443/media?id=cat',
]) {
    void test(`does not autoplay an unsafe verified GIF source: ${source}`, () => {
        assert.equal(isGifPlaying({ ...playback, source }), false);
    });
}

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
    assert.equal(
        canLoadGifPoster('https://media.giphy.com/media/cat/giphy.gif'),
        true,
    );
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
    assert.equal(
        isGifPlaying({ ...external, reducedMotion: true, playback: 'play' }),
        true,
    );
    assert.equal(isGifPlaying({ ...external, playback: 'play' }), true);
    assert.equal(isGifPlaying({ ...external, playback: 'stop' }), false);
    assert.equal(
        isGifPlaying({ ...external, playback: 'play', isVisible: false }),
        false,
    );
});

void test('a failed GIF stays stopped until the user retries it', () => {
    const source = 'https://example.com/cat.gif';
    const failed = failGifLoad(
        { source, failedSource: null, retryCount: 0, playback: 'auto' },
        source,
    );

    assert.equal(hasGifLoadFailed(failed, source), true);
    assert.equal(isGifPlaying({ ...playback, source, hasFailed: true }), false);
    assert.equal(canRetryGifLoad(failed, source), true);

    const retried = retryGifLoad(failed, source);
    assert.equal(hasGifLoadFailed(retried, source), false);
    assert.equal(retried.retryCount, 1);
    assert.equal(retried.playback, 'play');
});

void test('a new GIF source gets a fresh retry budget without changing playback preference', () => {
    const source = 'https://example.com/cat.gif';
    const replacement = 'https://example.com/dog.gif';
    const failed = failGifLoad(
        {
            source,
            failedSource: null,
            retryCount: MAX_GIF_RETRIES,
            playback: 'stop',
        },
        source,
    );

    const replacementState = syncGifLoadRetryState(failed, replacement);
    assert.equal(hasGifLoadFailed(replacementState, replacement), false);
    assert.equal(replacementState.retryCount, 0);
    assert.equal(replacementState.playback, 'stop');
    assert.equal(canRetryGifLoad(replacementState, replacement), false);

    const failedReplacement = failGifLoad(replacementState, replacement);
    assert.equal(canRetryGifLoad(failedReplacement, replacement), true);

    const originalState = syncGifLoadRetryState(failedReplacement, source);
    assert.equal(hasGifLoadFailed(originalState, source), false);
    assert.equal(originalState.retryCount, 0);
    assert.equal(canRetryGifLoad(originalState, source), false);
});

void test('GIF retries are bounded and stale failures cannot affect a replacement source', () => {
    const source = 'https://example.com/cat.gif';
    const replacement = 'https://example.com/dog.gif';
    let state: GifLoadRetryState = {
        source,
        failedSource: null,
        retryCount: 0,
        playback: 'auto' as const,
    };

    for (let retry = 0; retry < MAX_GIF_RETRIES; retry++) {
        state = failGifLoad(state, source);
        assert.equal(canRetryGifLoad(state, source), true);
        state = retryGifLoad(state, source);
    }

    state = failGifLoad(state, source);
    assert.equal(hasGifLoadFailed(state, source), true);
    assert.equal(canRetryGifLoad(state, source), false);
    assert.equal(retryGifLoad(state, source), state);

    const active = syncGifLoadRetryState(state, replacement);
    assert.equal(failGifLoad(active, source), active);
    assert.equal(hasGifLoadFailed(active, replacement), false);
});
