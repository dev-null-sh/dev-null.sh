import assert from 'node:assert/strict';
import { test, vi } from 'vite-plus/test';
import type { CommitPhoto } from '../../resources/js/lib/commit-photo';

let page: { url: string; props: { photo?: Partial<CommitPhoto> } };
vi.doMock('@inertiajs/react', () => ({ usePage: () => page }));
const { useCurrentUrl } =
    await import('../../resources/js/hooks/use-current-url');

for (const source of [
    '/log/latest?since=first',
    '/@reader',
    '/settings/profile',
]) {
    test(`keeps ${source} selected while its photo viewer is open`, () => {
        page = {
            url: '/@author/commits/photo-post/photos/1',
            props: { photo: { backgroundUrl: `http://localhost${source}` } },
        };
        const current = useCurrentUrl();
        assert.equal(current.currentUrl, source.split('?')[0]);
        assert.equal(current.isCurrentUrl(source.split('?')[0]), true);
        assert.equal(
            current.isCurrentOrParentUrl(
                source.split('/').slice(0, 2).join('/'),
            ),
            true,
        );
    });
}

test('uses the page URL without a photo background, including direct photo links', () => {
    for (const photo of [undefined, { index: 0 }]) {
        page = {
            url: '/@author/commits/photo-post/photos/1',
            props: { photo },
        };
        const current = useCurrentUrl();
        assert.equal(current.currentUrl, page.url);
        assert.equal(current.isCurrentOrParentUrl('/log'), false);
    }
});
