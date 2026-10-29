/**
 * Voice notes travel as Opus in WebM at 24 kbps, about 180 KB a minute, straight from the browser's recorder. A
 * browser that can't record Opus decodes what it recorded and sends 16 kHz, mono, 16-bit PCM WAV instead. The server
 * checks both without decoding them, so the waveform is drawn here. Keep the limits in sync with
 * App\Services\VoiceNotes.
 */
const opusType = 'audio/webm;codecs=opus';

const opusBitsPerSecond = 24_000;

export const voiceSampleRate = 16_000;

export const minVoiceMs = 500;

export const maxVoiceMs = 60_000;

export const voiceBars = 48;

export const voiceSpeeds = [1, 1.5, 2];

/**
 * A recorded voice note that hasn't been pushed yet.
 */
export type VoiceDraft = {
    file: File;
    duration_ms: number;
    waveform: number[];
};

export class VoiceNoteError extends Error {}

/**
 * The samples, from -1 to 1, as a WAV file holding nothing else.
 */
export function encodeWav(samples: Float32Array): ArrayBuffer {
    const buffer = new ArrayBuffer(44 + samples.length * 2);
    const view = new DataView(buffer);
    const text = (offset: number, value: string) =>
        new TextEncoder()
            .encode(value)
            .forEach((byte, index) => view.setUint8(offset + index, byte));

    text(0, 'RIFF');
    view.setUint32(4, 36 + samples.length * 2, true);
    text(8, 'WAVEfmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, voiceSampleRate, true);
    view.setUint32(28, voiceSampleRate * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    text(36, 'data');
    view.setUint32(40, samples.length * 2, true);

    samples.forEach((sample, index) => {
        const clamped = Math.max(-1, Math.min(1, sample));

        view.setInt16(
            44 + index * 2,
            clamped < 0 ? clamped * 0x8000 : clamped * 0x7fff,
            true,
        );
    });

    return buffer;
}

/**
 * The loudest sample of each slice, from 0 to 100, drawn the way the server draws it once the note is pushed.
 */
export function waveformOf(samples: Float32Array, bars = voiceBars): number[] {
    return Array.from({ length: bars }, (_, bar) => {
        const from = Math.floor((samples.length * bar) / bars);
        const to = Math.floor((samples.length * (bar + 1)) / bars);
        let peak = 0;

        for (let index = from; index < to; index++) {
            peak = Math.max(peak, Math.abs(samples[index] ?? 0));
        }

        return Math.round(Math.sqrt(Math.min(peak, 1)) * 100);
    });
}

/**
 * Levels from 0 to 1, taken while recording, as many bars as a waveform has: the loudest of each slice.
 */
export function barsOf(levels: number[], bars = voiceBars): number[] {
    return Array.from({ length: bars }, (_, bar) => {
        const from = Math.floor((levels.length * bar) / bars);
        const to = Math.max(
            from + 1,
            Math.floor((levels.length * (bar + 1)) / bars),
        );

        return Math.round(
            Math.min(1, Math.max(0, ...levels.slice(from, to))) * 100,
        );
    });
}

let silence: string | null = null;

/**
 * A tenth of a second of silence, for unlocking an audio element on iOS while the real audio downloads.
 */
export function silenceUrl(): string {
    silence ??= URL.createObjectURL(
        new Blob([encodeWav(new Float32Array(voiceSampleRate / 10))], {
            type: 'audio/wav',
        }),
    );

    return silence;
}

/**
 * "0:07", "1:00".
 */
export function formatVoiceTime(ms: number): string {
    const seconds = Math.max(0, Math.floor(ms / 1000));

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

/**
 * The saved playback volume as a whole percent, at full volume until a sensible one is saved.
 */
export function voiceVolumeOf(saved: string): number {
    const volume = Number(saved);

    return saved === '' || !Number.isFinite(volume)
        ? 1
        : Math.round(Math.min(1, Math.max(0, volume)) * 100) / 100;
}

export function canRecordVoice(): boolean {
    return (
        typeof MediaRecorder !== 'undefined' &&
        typeof OfflineAudioContext !== 'undefined' &&
        navigator.mediaDevices?.getUserMedia !== undefined
    );
}

/**
 * A recording in progress. The microphone is released as soon as it stops or is cancelled.
 */
export type VoiceRecording = {
    /**
     * When the recorder really started capturing, on the clock of `performance.now()` and animation frames.
     */
    startedAt: number;
    /**
     * How loud the microphone is right now, from 0 to 1.
     */
    level: () => number;
    stop: () => Promise<VoiceDraft>;
    cancel: () => void;
};

export async function startVoiceRecording(): Promise<VoiceRecording> {
    let stream: MediaStream;

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            audio: {
                channelCount: 1,
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true,
            },
        });
    } catch (error) {
        const name = error instanceof DOMException ? error.name : '';

        throw new VoiceNoteError(
            name === 'NotAllowedError' || name === 'SecurityError'
                ? 'fatal: microphone access denied'
                : name === 'NotFoundError'
                  ? 'fatal: no microphone found'
                  : 'fatal: could not open the microphone',
        );
    }

    // The recorder starts before anything else is set up, since opening the level meter's audio context can take a
    // moment, and every one of them would be cut from the start of the note.
    const recorder = new MediaRecorder(
        stream,
        MediaRecorder.isTypeSupported(opusType)
            ? { mimeType: opusType, audioBitsPerSecond: opusBitsPerSecond }
            : {},
    );
    const chunks: Blob[] = [];
    const started = new Promise<number>((resolve, reject) => {
        recorder.onstart = () => resolve(performance.now());
        recorder.onerror = () =>
            reject(new VoiceNoteError('fatal: could not start recording'));
    });
    const stopped = new Promise<Blob>((resolve) => {
        recorder.ondataavailable = (event) => chunks.push(event.data);
        recorder.onstop = () =>
            resolve(new Blob(chunks, { type: recorder.mimeType }));
    });

    recorder.start();

    const meter = new AudioContext();
    const analyser = meter.createAnalyser();
    const frame = new Float32Array(analyser.fftSize);

    meter.createMediaStreamSource(stream).connect(analyser);
    void meter.resume().catch(() => undefined);

    const level = () => {
        analyser.getFloatTimeDomainData(frame);

        return Math.sqrt(
            frame.reduce((peak, sample) => Math.max(peak, Math.abs(sample)), 0),
        );
    };
    const levels: number[] = [];
    const sampling = window.setInterval(() => levels.push(level()), 50);

    const release = () => {
        window.clearInterval(sampling);
        stream.getTracks().forEach((track) => track.stop());
        void meter.close().catch(() => undefined);
    };

    let startedAt: number;

    try {
        startedAt = await started;
    } catch (error) {
        release();

        throw error;
    }

    return {
        startedAt,
        level,
        stop: async () => {
            const duration = Math.min(
                maxVoiceMs,
                Math.round(performance.now() - startedAt),
            );

            recorder.stop();
            release();

            const recording = await stopped;

            if (duration < minVoiceMs) {
                throw new VoiceNoteError(
                    'fatal: voice note shorter than half a second',
                );
            }

            return recording.type.startsWith('audio/webm') &&
                recording.type.includes('opus')
                ? {
                      file: new File([recording], 'voice-note.webm', {
                          type: 'audio/webm',
                      }),
                      duration_ms: duration,
                      waveform: barsOf(levels),
                  }
                : wavDraftOf(recording);
        },
        cancel: () => {
            if (recorder.state !== 'inactive') {
                recorder.stop();
            }

            release();
        },
    };
}

/**
 * Decoding into a 16 kHz context resamples the recording on the way, whatever rate the microphone ran at.
 */
async function wavDraftOf(recording: Blob): Promise<VoiceDraft> {
    let audio: AudioBuffer;

    try {
        audio = await new OfflineAudioContext(
            1,
            1,
            voiceSampleRate,
        ).decodeAudioData(await recording.arrayBuffer());
    } catch {
        throw new VoiceNoteError('fatal: could not read the recording');
    }

    const length = Math.min(
        audio.length,
        (voiceSampleRate * maxVoiceMs) / 1000,
    );
    const samples = new Float32Array(length);

    for (let channel = 0; channel < audio.numberOfChannels; channel++) {
        const data = audio.getChannelData(channel);

        for (let index = 0; index < length; index++) {
            samples[index] =
                (samples[index] ?? 0) +
                (data[index] ?? 0) / audio.numberOfChannels;
        }
    }

    const duration = Math.floor((length * 1000) / voiceSampleRate);

    if (duration < minVoiceMs) {
        throw new VoiceNoteError(
            'fatal: voice note shorter than half a second',
        );
    }

    return {
        file: new File([encodeWav(samples)], 'voice-note.wav', {
            type: 'audio/wav',
        }),
        duration_ms: duration,
        waveform: waveformOf(samples),
    };
}
