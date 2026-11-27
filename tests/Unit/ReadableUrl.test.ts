import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readableUrl } from '../../resources/js/lib/urls.ts';

await test('shows the visible non-ascii characters of a url as themselves', () => {
    assert.equal(
        readableUrl('https://fr.wikipedia.org/wiki/%C3%89t%C3%A9'),
        'https://fr.wikipedia.org/wiki/Été',
    );
});

await test('keeps ascii, invisible and control characters and invalid utf-8 escaped', () => {
    for (const url of [
        'https://example.com/a%20b%2Fc?q=100%25',
        'https://example.com/%C3%28%FF',
        'https://example.com/%E2%80%AEgnp.exe',
        'https://example.com/a%E2%80%8Bb%C2%A0c',
    ]) {
        assert.equal(readableUrl(url), url);
    }
});
