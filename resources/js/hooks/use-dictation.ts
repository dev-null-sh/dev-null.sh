import type { RefObject } from 'react';
import {
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';
import { toast } from 'sonner';
import { dictatedText } from '@/lib/dictation';
import { insertAtSelection } from '@/lib/text-selection';

type SpeechRecognitionResultList = ArrayLike<
    ArrayLike<{ transcript: string }> & { isFinal: boolean }
>;

/**
 * The part of the Web Speech API's SpeechRecognition used here, which TypeScript's DOM types don't include.
 */
type Recognition = {
    lang: string;
    continuous: boolean;
    interimResults: boolean;
    onresult:
        | ((event: {
              resultIndex: number;
              results: SpeechRecognitionResultList;
          }) => void)
        | null;
    onerror: ((event: { error: string }) => void) | null;
    onend: (() => void) | null;
    start: () => void;
    stop: () => void;
    abort: () => void;
};

type RecognitionConstructor = new () => Recognition;

function recognitionConstructor(): RecognitionConstructor | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const speech = window as unknown as {
        SpeechRecognition?: RecognitionConstructor;
        webkitSpeechRecognition?: RecognitionConstructor;
    };

    return speech.SpeechRecognition ?? speech.webkitSpeechRecognition ?? null;
}

const subscribe = () => () => {};

/**
 * Dictation into the composer's textarea with the browser's own speech recognition (Chrome, Edge and Safari have it,
 * Firefox doesn't). Each finished phrase is inserted where the caret is, as typing would, up to `maxLength`.
 */
export function useDictation({
    body,
    updateBody,
    textareaRef,
    maxLength,
}: {
    body: string;
    updateBody: (body: string) => void;
    textareaRef: RefObject<HTMLTextAreaElement | null>;
    maxLength: number;
}): {
    isSupported: boolean;
    isListening: boolean;
    start: () => void;
    stop: () => void;
} {
    // Unknown while rendering on the server, so the button only appears once the page runs in the browser.
    const isSupported = useSyncExternalStore(
        subscribe,
        () => recognitionConstructor() !== null,
        () => false,
    );
    const [isListening, setIsListening] = useState(false);
    const recognition = useRef<Recognition | null>(null);
    const latest = useRef({ body, updateBody, maxLength });
    const caret = useRef<number | null>(null);

    useLayoutEffect(() => {
        latest.current = { body, updateBody, maxLength };

        const element = textareaRef.current;

        if (caret.current === null || element === null) {
            return;
        }

        element.focus();
        element.setSelectionRange(caret.current, caret.current);
        caret.current = null;
    }, [body, updateBody, maxLength, textareaRef]);

    useEffect(() => () => recognition.current?.abort(), []);

    const insert = (transcript: string) => {
        const { body, updateBody, maxLength } = latest.current;
        const element = textareaRef.current;
        const text = dictatedText(
            body.slice(0, element?.selectionStart ?? body.length),
            transcript,
        );
        const inserted =
            text === ''
                ? null
                : insertAtSelection(body, element, text, maxLength);

        if (inserted !== null) {
            caret.current = inserted.caret;
            updateBody(inserted.value);
        }
    };

    const start = () => {
        const Constructor = recognitionConstructor();

        if (Constructor === null || recognition.current !== null) {
            return;
        }

        const listener = new Constructor();
        listener.lang = document.documentElement.lang || navigator.language;
        listener.continuous = true;
        listener.interimResults = false;
        listener.onresult = (event) => {
            for (
                let index = event.resultIndex;
                index < event.results.length;
                index++
            ) {
                const result = event.results[index];

                if (result.isFinal) {
                    insert(result[0].transcript);
                }
            }
        };
        listener.onerror = (event) => {
            if (
                event.error === 'not-allowed' ||
                event.error === 'service-not-allowed'
            ) {
                toast.error('Allow the microphone to dictate your commit.');
            } else if (
                event.error !== 'no-speech' &&
                event.error !== 'aborted'
            ) {
                toast.error('Dictation stopped. Try again.');
            }
        };
        listener.onend = () => {
            recognition.current = null;
            setIsListening(false);
        };

        recognition.current = listener;
        listener.start();
        setIsListening(true);
    };

    const stop = () => recognition.current?.stop();

    return { isSupported, isListening, start, stop };
}
