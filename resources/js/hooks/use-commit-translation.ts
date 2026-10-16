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
    checkTranslation,
    isTranslationSupported,
    replaceTexts,
    subscribeTranslation,
    translateBody,
    translatableTexts,
    translationLanguages,
    translationMode,
} from '@/lib/translation';
import type { TranslationStatus } from '@/lib/translation';
import type { BodySegment } from '@/types';

/** The server has no translation APIs, so it never renders a control. */
const noMode = () => 'unknown' as const;
const noLanguages = () => null;

/**
 * The language to start from: the wanted one when the browser offers it, and
 * otherwise the first one it does.
 */
function preferred(
    languages: string[] | null,
    wanted: string,
    fallback: string,
): string {
    if (languages === null || languages.includes(wanted)) {
        return wanted;
    }

    return languages.at(0) ?? fallback;
}

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
    /** True in browsers that can translate this body, for a body with text. */
    readonly canTranslate: boolean;
    readonly phase: TranslationPhase;
    /** Download progress of the models, from 0 to 1, while one downloads. */
    readonly progress: number | null;
    /** Language the commit is written in, once the browser has detected it. */
    readonly language: string | null;
    /** Language the reader reads in, the one a commit is translated into. */
    readonly target: string;
    /** Language a commit is translated from, when the reader picks it. */
    readonly source: string;
    /** The languages to pick from, or null while the browser detects them. */
    readonly languages: string[] | null;
    readonly setSource: (language: string) => void;
    readonly setTarget: (language: string) => void;
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
    const [choice, setChoice] = useState<{
        source: string;
        target: string;
    } | null>(null);
    const isMounted = useRef(true);
    const isWorking = useRef(false);

    // A browser can ship the APIs and still have no detection model, so the
    // control waits for the browser's answer, and asks the reader for the
    // languages when it cannot detect them. A commit that is only a code block
    // has nothing to translate.
    const mode = useSyncExternalStore(
        subscribeTranslation,
        translationMode,
        noMode,
    );
    const languages = useSyncExternalStore(
        subscribeTranslation,
        translationLanguages,
        noLanguages,
    );

    const browser = browserLanguage();
    const source = choice?.source ?? preferred(languages, 'en', browser);
    const target = choice?.target ?? preferred(languages, browser, browser);

    const canTranslate =
        isEnabled &&
        translatableTexts(segments).some((text) => text.trim() !== '') &&
        (mode === 'automatic' || (mode === 'manual' && languages !== null));

    useEffect(() => {
        isMounted.current = true;

        if (isEnabled) {
            void checkTranslation();
        }

        return () => {
            isMounted.current = false;
        };
    }, [isEnabled]);

    const translate = useCallback(() => {
        if (isWorking.current || !isTranslationSupported()) {
            return;
        }

        isWorking.current = true;
        setIsShowingOriginal(false);
        setState({ ...idle, phase: 'working' });

        void translateBody(emojified(segments), target, {
            source: languages === null ? undefined : source,
            onProgress: (progress) => {
                if (isMounted.current) {
                    setState((current) => ({ ...current, progress }));
                }
            },
        })
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
    }, [segments, source, target, languages]);

    const isTranslated = state.texts !== null && !isShowingOriginal;

    return {
        canTranslate,
        phase: state.phase,
        progress: state.progress,
        language: state.language,
        target,
        source,
        languages,
        setSource: (language) => setChoice({ source: language, target }),
        setTarget: (language) => setChoice({ source, target: language }),
        isTranslated,
        segments: isTranslated
            ? replaceTexts(segments, state.texts ?? [])
            : segments,
        translate,
        showOriginal: () => setIsShowingOriginal(true),
        showTranslation: () => setIsShowingOriginal(false),
    };
}
