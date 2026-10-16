// The browser's own translation, the Web Translation API. Chrome and Edge ship
// it, the models run on the device, and no text leaves the browser. Everywhere
// else both globals stay undefined and the interface shows no control.
// https://developer.mozilla.org/docs/Web/API/Translator_and_Language_Detector_APIs

/** Receives an event target that reports the model download. */
export type TranslationMonitor = (monitor: EventTarget) => void;

export type TranslationSession = {
    destroy: () => void;
};

export type TranslatorSession = TranslationSession & {
    translate: (text: string) => Promise<string>;
};

export type TranslatorApi = {
    create: (options: {
        sourceLanguage: string;
        targetLanguage: string;
        monitor?: TranslationMonitor;
    }) => Promise<TranslatorSession>;
};

export type LanguageDetection = {
    detectedLanguage: string;
    confidence: number;
};

export type LanguageDetectorSession = TranslationSession & {
    detect: (text: string) => Promise<LanguageDetection[]>;
};

export type LanguageDetectorApi = {
    create: (options?: {
        monitor?: TranslationMonitor;
    }) => Promise<LanguageDetectorSession>;
};

declare global {
    var Translator: TranslatorApi | undefined;
    var LanguageDetector: LanguageDetectorApi | undefined;
}
