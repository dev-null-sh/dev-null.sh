import assert from 'node:assert/strict';
import { afterEach, test, vi } from 'vite-plus/test';
import { measureCommitSize } from '../../resources/js/lib/commit-size';

afterEach(() => vi.unstubAllGlobals());

for (const height of [151.5, 505]) {
    test(`gives a remounted offscreen card its actual ${height}px height before scroll restoration`, () => {
        vi.stubGlobal('getComputedStyle', () => ({
            contentVisibility: 'auto',
        }));
        const card = {
            style: { contentVisibility: '', containIntrinsicBlockSize: '' },
            getBoundingClientRect: () => ({
                height:
                    card.style.contentVisibility === 'visible' ? height : 320,
            }),
        } as unknown as HTMLElement;
        measureCommitSize(card);
        assert.equal(card.style.containIntrinsicBlockSize, `auto ${height}px`);
        assert.equal(card.style.contentVisibility, '');
    });
}
test('leaves mobile cards without containment unchanged and handles unmounts', () => {
    vi.stubGlobal('getComputedStyle', () => ({ contentVisibility: 'visible' }));
    const card = {
        style: { contentVisibility: '', containIntrinsicBlockSize: '' },
        getBoundingClientRect: () => {
            throw new Error('Uncontained cards do not need measurement');
        },
    } as unknown as HTMLElement;
    measureCommitSize(card);
    measureCommitSize(null);
    assert.equal(card.style.containIntrinsicBlockSize, '');
});
