import assert from 'node:assert/strict';
import { test } from 'node:test';
import { sizedAvatarUrl } from '../../resources/js/lib/urls.ts';

await test('asks GitHub for the avatar at the size it is drawn', () => {
    assert.equal(
        sizedAvatarUrl('https://avatars.githubusercontent.com/u/583231?v=4', 80),
        'https://avatars.githubusercontent.com/u/583231?v=4&s=80',
    );
    assert.equal(
        sizedAvatarUrl('https://avatars.githubusercontent.com/u/583231?v=4&s=460', 160),
        'https://avatars.githubusercontent.com/u/583231?v=4&s=160',
    );
});

await test('leaves avatars hosted anywhere else as they are', () => {
    for (const url of ['https://dev-null.sh/images/tom.webp', '/images/tom.webp', 'not a url']) {
        assert.equal(sizedAvatarUrl(url, 80), url);
    }
});
