/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement } from 'react';
import { afterEach, test, vi } from 'vite-plus/test';

afterEach(() => vi.unstubAllGlobals());

vi.doMock('react', () => ({
    ...React,
    useRef: (initial: unknown) => ({ current: initial }),
}));

const { VoiceSpeed } =
    await import('../../resources/js/components/chat/voice-speed');

test('each press plays the voice note at the next speed, back to normal after 2×', () => {
    vi.stubGlobal('window', {
        matchMedia: () => ({ matches: true }),
    });
    const pressed = (speed: number) => {
        let next: number | undefined;
        const button = VoiceSpeed({
            speed,
            onSpeedChange: (to) => (next = to),
        }) as ReactElement<{ onClick: () => void; 'aria-label': string }>;

        button.props.onClick();

        return [button.props['aria-label'], next];
    };

    assert.deepEqual(pressed(1), ['Playback speed, 1×', 1.5]);
    assert.deepEqual(pressed(1.5), ['Playback speed, 1.5×', 2]);
    assert.deepEqual(pressed(2), ['Playback speed, 2×', 1]);
});
