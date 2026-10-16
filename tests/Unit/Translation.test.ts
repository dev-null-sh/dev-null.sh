/// <reference types="node" />

import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    baseLanguage,
    languageName,
    replaceTexts,
    translateBody,
    translatableTexts,
} from '../../resources/js/lib/translation.ts';
import type { BodySegment } from '../../resources/js/types';
import type {
    LanguageDetection,
    LanguageDetectorApi,
    TranslationMonitor,
    TranslatorApi,
} from '../../resources/js/types/translation.ts';

const segments: BodySegment[] = [
    { type: 'text', text: 'Bonjour le monde' },
    { type: 'code', code: 'php artisan migrate' },
];

const french: LanguageDetection[] = [
    { detectedLanguage: 'fr-FR', confidence: 0.92 },
];

/**
 * Answer every detection and translation with the given results, and record the
 * language pairs a session was created for. `downloads` is what the browser
 * reports while it downloads a model.
 */
function stubBrowser(
    detections: LanguageDetection[],
    translate: (text: string) => string = (text) => text.toUpperCase(),
    downloads: TranslationMonitor = () => undefined,
): { pairs: string[] } {
    const pairs: string[] = [];
    const reportDownload = (monitor: TranslationMonitor | undefined) => {
        if (monitor === undefined) {
            return;
        }

        const target = new EventTarget();
        monitor(target);
        downloads(target);
    };

    const detector: LanguageDetectorApi = {
        create: (options) => {
            reportDownload(options?.monitor);

            return Promise.resolve({
                detect: () => Promise.resolve(detections),
                destroy: () => undefined,
            });
        },
    };

    const translator: TranslatorApi = {
        create: (options) => {
            pairs.push(`${options.sourceLanguage}->${options.targetLanguage}`);
            reportDownload(options.monitor);

            return Promise.resolve({
                translate: (text: string) => Promise.resolve(translate(text)),
                destroy: () => undefined,
            });
        },
    };

    globalThis.LanguageDetector = detector;
    globalThis.Translator = translator;

    return { pairs };
}

function clearBrowser(): void {
    globalThis.LanguageDetector = undefined;
    globalThis.Translator = undefined;
}

void test('reads a base language out of a locale and names it', () => {
    assert.equal(baseLanguage('fr-FR'), 'fr');
    assert.equal(baseLanguage('EN'), 'en');
    assert.equal(languageName('fr', 'en'), 'French');
    assert.equal(languageName('de', 'en'), 'German');
});

void test('translates text segments and leaves code as written', () => {
    assert.deepEqual(translatableTexts(segments), ['Bonjour le monde']);
    assert.deepEqual(replaceTexts(segments, ['Hello world']), [
        { type: 'text', text: 'Hello world' },
        { type: 'code', code: 'php artisan migrate' },
    ]);
});

void test('keeps a segment when the translation is missing', () => {
    assert.deepEqual(replaceTexts(segments, []), segments);
});

void test('translates a body written in another language', async (t) => {
    t.after(clearBrowser);
    const { pairs } = stubBrowser(french);

    const outcome = await translateBody(segments, 'en');

    assert.equal(outcome.status, 'translated');
    assert.equal(outcome.language, 'fr');
    assert.deepEqual(outcome.texts, ['BONJOUR LE MONDE']);
    assert.deepEqual(pairs, ['fr->en']);
});

void test('creates one session per language pair', async (t) => {
    t.after(clearBrowser);
    const { pairs } = stubBrowser([{ detectedLanguage: 'de', confidence: 0.8 }]);

    await translateBody(segments, 'en');
    await translateBody(segments, 'en');

    assert.deepEqual(pairs, ['de->en']);
});

void test('reports the model downloads as they go', async (t) => {
    t.after(clearBrowser);
    const progress: number[] = [];

    stubBrowser(
        [{ detectedLanguage: 'es', confidence: 0.8 }],
        undefined,
        (monitor) => {
            for (const loaded of [0.25, 0.75]) {
                monitor.dispatchEvent(
                    Object.assign(new Event('downloadprogress'), { loaded }),
                );
            }
        },
    );

    await translateBody(segments, 'en', (value) => progress.push(value));

    // The detection model and the translation model report through one listener.
    assert.deepEqual(progress, [0.25, 0.75, 0.25, 0.75]);
});

void test('leaves a body already in the reader language alone', async (t) => {
    t.after(clearBrowser);
    const { pairs } = stubBrowser([{ detectedLanguage: 'en', confidence: 0.9 }]);

    const outcome = await translateBody(segments, 'en');

    assert.equal(outcome.status, 'same-language');
    assert.equal(outcome.language, 'en');
    assert.deepEqual(outcome.texts, ['Bonjour le monde']);
    assert.deepEqual(pairs, []);
});

void test('does not guess a language the browser is unsure about', async (t) => {
    t.after(clearBrowser);
    const { pairs } = stubBrowser([{ detectedLanguage: 'fr', confidence: 0.3 }]);

    const outcome = await translateBody(segments, 'en');

    assert.equal(outcome.status, 'unknown-language');
    assert.equal(outcome.language, null);
    assert.deepEqual(outcome.texts, ['Bonjour le monde']);
    assert.deepEqual(pairs, []);
});

void test('reports a browser without the APIs', async (t) => {
    t.after(clearBrowser);
    clearBrowser();

    const outcome = await translateBody(segments, 'en');

    assert.equal(outcome.status, 'unsupported');
    assert.equal(outcome.language, null);
});
