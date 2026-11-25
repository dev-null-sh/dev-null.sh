import assert from 'node:assert/strict';
import { test } from 'node:test';
import { startVoiceRecording } from '../../resources/js/lib/voice-notes.ts';

const failures = [
    'recorder construction',
    'recorder start',
    'audio context construction',
    'analyser setup',
    'stream wiring',
] as const;

for (const failure of failures) {
    void test(`releases recording resources when ${failure} fails`, async () => {
        const originals = {
            navigator: Object.getOwnPropertyDescriptor(globalThis, 'navigator'),
            MediaRecorder: Object.getOwnPropertyDescriptor(
                globalThis,
                'MediaRecorder',
            ),
            AudioContext: Object.getOwnPropertyDescriptor(
                globalThis,
                'AudioContext',
            ),
        };
        let lastRecorder: Recorder | null = null;
        let stoppedTracks = 0;
        let stoppedRecorder = 0;
        let closedContexts = 0;
        const stream = {
            getTracks: () => [
                {
                    stop: () => {
                        stoppedTracks++;
                        if (failure === 'recorder start') {
                            throw new Error('track release failed');
                        }
                    },
                },
                { stop: () => stoppedTracks++ },
            ],
        };

        class Recorder {
            static isTypeSupported() {
                return true;
            }
            state = 'inactive';
            onstart: (() => void) | null = null;
            onerror: (() => void) | null = null;
            ondataavailable: ((event: { data: Blob }) => void) | null = null;
            onstop: (() => void) | null = null;

            constructor() {
                if (failure === 'recorder construction') {
                    throw new Error('recorder unavailable');
                }

                lastRecorder = this;
            }

            start() {
                this.state = 'recording';
                if (failure === 'recorder start') {
                    throw new Error('recorder start failed');
                }
            }

            stop() {
                this.state = 'inactive';
                stoppedRecorder++;
                if (failure === 'recorder start') {
                    throw new Error('recorder stop failed');
                }
            }
        }

        class Meter {
            constructor() {
                if (failure === 'audio context construction') {
                    throw new Error('audio context unavailable');
                }
            }

            createAnalyser() {
                if (failure === 'analyser setup') {
                    throw new Error('analyser unavailable');
                }

                return {
                    fftSize: 1,
                    getFloatTimeDomainData: () => undefined,
                };
            }

            createMediaStreamSource() {
                return {
                    connect: () => {
                        if (failure === 'stream wiring') {
                            throw new Error('stream wiring failed');
                        }
                    },
                };
            }

            resume() {
                return Promise.resolve();
            }
            close() {
                closedContexts++;
                return Promise.resolve();
            }
        }

        try {
            Object.defineProperty(globalThis, 'navigator', {
                configurable: true,
                value: { mediaDevices: { getUserMedia: async () => stream } },
            });
            Object.defineProperty(globalThis, 'MediaRecorder', {
                configurable: true,
                value: Recorder,
            });
            Object.defineProperty(globalThis, 'AudioContext', {
                configurable: true,
                value: Meter,
            });

            await assert.rejects(startVoiceRecording());
            assert.equal(stoppedTracks, 2);
            // Synchronous setup failures must not leave a queued recorder error
            // able to reject the unobserved startup promise.
            assert.equal(lastRecorder?.onerror ?? null, null);
            assert.equal(
                stoppedRecorder,
                failure === 'recorder construction' ? 0 : 1,
            );
            assert.equal(
                closedContexts,
                ['analyser setup', 'stream wiring'].includes(failure) ? 1 : 0,
            );
        } finally {
            for (const [name, descriptor] of Object.entries(originals)) {
                if (descriptor === undefined) {
                    Reflect.deleteProperty(globalThis, name);
                } else {
                    Object.defineProperty(globalThis, name, descriptor);
                }
            }
        }
    });
}

test('continues audio capture but reports an unavailable meter when resume fails', async () => {
    const originals = Object.fromEntries(
        ['navigator', 'window', 'MediaRecorder', 'AudioContext'].map((name) => [
            name,
            Object.getOwnPropertyDescriptor(globalThis, name),
        ]),
    );
    let tracksStopped = 0;
    let recorderStopped = 0;
    let meterClosed = 0;
    const stream = {
        getTracks: () => [{ stop: () => tracksStopped++ }],
    };

    class Recorder {
        static isTypeSupported() {
            return true;
        }
        state = 'inactive';
        mimeType = 'audio/webm;codecs=opus';
        onstart: (() => void) | null = null;
        onerror: (() => void) | null = null;
        ondataavailable: ((event: { data: Blob }) => void) | null = null;
        onstop: (() => void) | null = null;
        start() {
            this.state = 'recording';
            queueMicrotask(() => this.onstart?.());
        }
        stop() {
            this.state = 'inactive';
            recorderStopped++;
            this.onstop?.();
        }
    }

    class Meter {
        createAnalyser() {
            return {
                fftSize: 1,
                getFloatTimeDomainData: () => {
                    throw new Error('unavailable meter should not be read');
                },
            };
        }
        createMediaStreamSource() {
            return { connect: () => undefined };
        }
        resume() {
            return Promise.reject(new Error('cannot resume meter'));
        }
        close() {
            meterClosed++;
            return Promise.resolve();
        }
    }

    try {
        Object.defineProperty(globalThis, 'navigator', {
            configurable: true,
            value: { mediaDevices: { getUserMedia: async () => stream } },
        });
        Object.defineProperty(globalThis, 'MediaRecorder', {
            configurable: true,
            value: Recorder,
        });
        Object.defineProperty(globalThis, 'AudioContext', {
            configurable: true,
            value: Meter,
        });
        Object.defineProperty(globalThis, 'window', {
            configurable: true,
            value: { setInterval: () => 1, clearInterval: () => undefined },
        });

        const recording = await startVoiceRecording();
        assert.equal(recording.level(), null);
        recording.cancel();
        assert.equal(tracksStopped, 1);
        assert.equal(recorderStopped, 1);
        assert.equal(meterClosed, 1);
    } finally {
        for (const [name, descriptor] of Object.entries(originals)) {
            if (descriptor === undefined) {
                Reflect.deleteProperty(globalThis, name);
            } else {
                Object.defineProperty(globalThis, name, descriptor);
            }
        }
    }
});
