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
