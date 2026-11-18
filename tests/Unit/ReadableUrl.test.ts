import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readableUrl } from '../../resources/js/lib/urls.ts';

await test('shows the non-ascii characters of a url as themselves', () => {
    assert.equal(
        readableUrl('https://fr.wikipedia.org/wiki/%C3%89t%C3%A9'),
        'https://fr.wikipedia.org/wiki/Été',
    );
});

await test('keeps ascii escapes and invalid utf-8 escaped', () => {
    for (const url of [
        'https://example.com/a%20b%2Fc?q=100%25',
        'https://example.com/%C3%28%FF',
    ]) {
        assert.equal(readableUrl(url), url);
    }
});
