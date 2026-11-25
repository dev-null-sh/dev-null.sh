import assert from 'node:assert/strict';
import { afterEach, test } from 'node:test';
import { scrollToFocusedCommit } from '../../resources/js/lib/focused-commit-scroll.ts';

const originals = new Map(['window', 'document', 'CSS', 'performance'].map((name) => [name, Object.getOwnPropertyDescriptor(globalThis, name)]));
afterEach(() => {
    for (const [name, descriptor] of originals) {
        if (descriptor) Object.defineProperty(globalThis, name, descriptor);
        else Reflect.deleteProperty(globalThis, name);
    }
});

function setup({ hash = '', load = 'navigate' } = {}) {
    const scrolled: { selector: string; options: ScrollIntoViewOptions }[] = [];
    const define = (name: string, value: unknown) => Object.defineProperty(globalThis, name, { configurable: true, value });
    define('window', { location: { hash } });
    define('CSS', { escape: (value: string) => value });
    define('performance', { getEntriesByType: () => [{ type: load }] });
    define('document', {
        querySelector: (selector: string) => ({
            scrollIntoView: (options: ScrollIntoViewOptions) => scrolled.push({ selector, options }),
        }),
    });
    return scrolled;
}

function navigation(type: string, props: Record<string, unknown> = {}, component = 'commit/show') {
    return { type, page: { component, props: { commit: { id: 'reply' }, ancestors: [{ id: 'root' }], ...props } } };
}

void test('brings the commit below its ancestors into view when its page opens', () => {
    for (const type of ['visit', 'initial']) {
        const scrolled = setup();
        scrollToFocusedCommit(navigation(type));
        assert.deepEqual(scrolled, [{ selector: '[data-commit="reply"]', options: { block: 'start' } }]);
    }
});

void test('keeps the restored position after Back, Forward or a reload', () => {
    for (const [type, load] of [['history', 'navigate'], ['initial', 'reload'], ['initial', 'back_forward']]) {
        const scrolled = setup({ load });
        scrollToFocusedCommit(navigation(type));
        assert.deepEqual(scrolled, []);
    }
});

void test('leaves the position alone without ancestors, with an anchor or a photo, or on other pages', () => {
    const cases: [ReturnType<typeof navigation>, string][] = [
        [navigation('visit', { ancestors: [] }), ''],
        [navigation('visit', { photo: { index: 0 } }), ''],
        [navigation('visit'), '#replies'],
        [navigation('visit', {}, 'log/show'), ''],
    ];
    for (const [event, hash] of cases) {
        const scrolled = setup({ hash });
        scrollToFocusedCommit(event);
        assert.deepEqual(scrolled, []);
    }
});
