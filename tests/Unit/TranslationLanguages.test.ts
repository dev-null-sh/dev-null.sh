import assert from 'node:assert/strict';
import { test } from 'node:test';
import { searchTranslationLanguages } from '../../resources/js/lib/translation-languages.ts';

const languages = [
    { code: 'hu', name: 'Hungarian', nativeName: 'magyar' },
    { code: 'ja', name: 'Japanese', nativeName: '日本語' },
    { code: 'de', name: 'German', nativeName: 'Deutsch' },
];

for (const query of ['Hungarian', 'magyar', '  MAGYAR  ', 'hung', 'hu']) {
    void test(`finds Hungarian by ${JSON.stringify(query)}`, () => {
        assert.deepEqual(searchTranslationLanguages(languages, query), [languages[0]]);
    });
}

void test('finds languages in their native script', () => {
    assert.deepEqual(searchTranslationLanguages(languages, '日本語'), [languages[1]]);
});

void test('returns every language for an empty search and none for an unknown name', () => {
    assert.deepEqual(searchTranslationLanguages(languages, ''), languages);
    assert.deepEqual(searchTranslationLanguages(languages, 'not-a-language'), []);
});
