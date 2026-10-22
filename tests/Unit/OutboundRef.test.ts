import assert from 'node:assert/strict';
import { test } from 'node:test';
import { withRef } from '../../resources/js/lib/urls.ts';

await test('tells outbound sites the visit came from /dev/null', () => {
    assert.equal(withRef('https://example.com'), 'https://example.com/?ref=dev-null.sh');
    assert.equal(withRef('https://example.com/post?id=1#top'), 'https://example.com/post?id=1&ref=dev-null.sh#top');
});

await test('leaves internal, GitHub and already tagged links as they are', () => {
    for (const url of [
        'https://dev-null.sh/@tom',
        'https://github.com/dev-null-sh/dev-null.sh',
        'https://gist.github.com/tom/1',
        'https://example.com/?ref=someone',
        'https://example.com/?utm_source=newsletter',
        'mailto:tom@example.com',
        'not a url',
    ]) {
        assert.equal(withRef(url), url);
    }
});
