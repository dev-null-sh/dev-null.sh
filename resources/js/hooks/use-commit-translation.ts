import {
    useCallback,
    useEffect,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';
import { emojify } from '@/lib/emoji';
import {
    browserLanguage,
    isTranslationSupported,
    replaceTexts,
    translateBody,
    translatableTexts,
} from '@/lib/translation';
import type { TranslationStatus } from '@/lib/translation';
import type { BodySegment } from '@/types';

const subscribe = () => () => undefined;

/**
 * Emoji shortcodes become emoji before the text is translated: a translator
 * leaves an emoji alone, where it rewrites ":tada:" into a word nothing maps
 * back to one.
 */
const emojified = (segments: BodySegment[]): BodySegment[] =>
    replaceTexts(
        segments,
        translatableTexts(segments).map((text) => emojify(text)),
    );

export type TranslationPhase =
    | 'idle'
    | 'working'
    | 'failed'
    | TranslationStatus;

type TranslationState = {
    phase: TranslationPhase;
    progress: number | null;
    language: string | null;
    texts: string[] | null;
};

const idle: TranslationState = {
    phase: 'idle',
    progress: null,
    language: null,
    texts: null,
};

export type CommitTranslation = {
    /** True in browsers that ship the translation APIs, for a body with text. */
    readonly canTranslate: boolean;
    readonly phase: TranslationPhase;
    /** Download progress of the models, from 0 to 1, while one downloads. */
    readonly progress: number | null;
    /** Language the commit is written in, once the browser has detected it. */
    readonly language: string | null;
    /** Language the reader's browser is in. */
    readonly target: string;
    readonly isTranslated: boolean;
    /** The segments to render: the translation while it is being shown. */
    readonly segments: BodySegment[];
    readonly translate: () => void;
    readonly showOriginal: () => void;
    readonly showTranslation: () => void;
};

/**
 * Translate a commit body with the browser's own translation, on demand, and
 * keep the original one click away.
 */
export function useCommitTranslation(
    segments: BodySegment[],
    isEnabled: boolean,
): CommitTranslation {
    const [state, setState] = useState(idle);
    const [isShowingOriginal, setIsShowingOriginal] = useState(false);
    const isMounted = useRef(true);
    const isWorking = useRef(false);

    // The server has no translation APIs, so it renders no control and the
    // client adds one after hydrating. A commit that is only a code block has
    // nothing to translate.
    const canTranslate =
        useSyncExternalStore(subscribe, isTranslationSupported, () => false) &&
        isEnabled &&
        translatableTexts(segments).some((text) => text.trim() !== '');

    useEffect(() => {
        isMounted.current = true;

        return () => {
            isMounted.current = false;
        };
    }, []);

    const translate = useCallback(() => {
        if (isWorking.current || !isTranslationSupported()) {
            return;
        }

        isWorking.current = true;
        setIsShowingOriginal(false);
        setState({ ...idle, phase: 'working' });

        void translateBody(
            emojified(segments),
            browserLanguage(),
            (progress) => {
                if (isMounted.current) {
                    setState((current) => ({ ...current, progress }));
                }
            },
        )
            .then((outcome) => {
                if (!isMounted.current) {
                    return;
                }

                setState({
                    phase: outcome.status,
                    progress: null,
                    language: outcome.language,
                    texts:
                        outcome.status === 'translated' ? outcome.texts : null,
                });
            })
            .catch(() => {
                if (isMounted.current) {
                    setState({ ...idle, phase: 'failed' });
                }
            })
            .finally(() => {
                isWorking.current = false;
            });
    }, [segments]);

    const isTranslated = state.texts !== null && !isShowingOriginal;

    return {
        canTranslate,
        phase: state.phase,
        progress: state.progress,
        language: state.language,
        target: browserLanguage(),
        isTranslated,
        segments: isTranslated
            ? replaceTexts(segments, state.texts ?? [])
            : segments,
        translate,
        showOriginal: () => setIsShowingOriginal(true),
        showTranslation: () => setIsShowingOriginal(false),
    };
}
