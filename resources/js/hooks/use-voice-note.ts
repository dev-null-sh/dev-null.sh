import { useCallback, useEffect, useRef, useState } from 'react';
import {
    canRecordVoice,
    startVoiceRecording,
    VoiceNoteError,
} from '@/lib/voice-notes';
import type { VoiceDraft, VoiceRecording } from '@/lib/voice-notes';

export type VoiceNoteState =
    | { status: 'idle' }
    | { status: 'starting' }
    | { status: 'recording'; startedAt: number }
    | { status: 'encoding' }
    | { status: 'recorded'; draft: VoiceDraft };

export type UseVoiceNoteReturn = {
    readonly state: VoiceNoteState;
    readonly start: () => Promise<void>;
    /**
     * Finishes the recording, resolving to it, or to null when there is none to push.
     */
    readonly stop: () => Promise<VoiceDraft | null>;
    readonly discard: () => void;
    readonly level: () => number;
};

const messageOf = (error: unknown): string =>
    error instanceof VoiceNoteError
        ? error.message
        : 'fatal: could not record a voice note';

/**
 * Records a voice note for the composer. The microphone is only open while recording, and closed again when the
 * recording stops, is discarded, or the composer goes away. Each start gets a new generation, so a microphone or
 * recording that only arrives after a discard is let go instead of shown.
 */
export function useVoiceNote(
    onError: (message: string) => void,
): UseVoiceNoteReturn {
    const [state, setState] = useState<VoiceNoteState>({ status: 'idle' });
    const recording = useRef<VoiceRecording | null>(null);
    const generation = useRef(0);
    const draft = useRef<VoiceDraft | null>(null);
    const reportError = useRef(onError);

    useEffect(() => {
        reportError.current = onError;
    });

    // Going away while the microphone is still opening lets go of it as soon as it opens.
    useEffect(
        () => () => {
            generation.current++;
            recording.current?.cancel();
        },
        [],
    );

    const start = async () => {
        const current = ++generation.current;

        if (!canRecordVoice()) {
            reportError.current("fatal: this browser can't record audio");

            return;
        }

        setState({ status: 'starting' });

        try {
            const started = await startVoiceRecording();

            if (current !== generation.current) {
                started.cancel();

                return;
            }

            recording.current = started;
            setState({ status: 'recording', startedAt: started.startedAt });
        } catch (error) {
            if (current === generation.current) {
                setState({ status: 'idle' });
                reportError.current(messageOf(error));
            }
        }
    };

    const stop = useCallback(async (): Promise<VoiceDraft | null> => {
        const started = recording.current;
        const current = generation.current;

        if (started === null) {
            return draft.current;
        }

        recording.current = null;
        setState({ status: 'encoding' });

        try {
            const recorded = await started.stop();

            if (current !== generation.current) {
                return null;
            }

            draft.current = recorded;
            setState({ status: 'recorded', draft: recorded });

            return recorded;
        } catch (error) {
            if (current === generation.current) {
                setState({ status: 'idle' });
                reportError.current(messageOf(error));
            }

            return null;
        }
    }, []);

    const discard = () => {
        generation.current++;
        recording.current?.cancel();
        recording.current = null;
        draft.current = null;
        setState({ status: 'idle' });
    };

    const level = useCallback(() => recording.current?.level() ?? 0, []);

    return { state, start, stop, discard, level };
}
