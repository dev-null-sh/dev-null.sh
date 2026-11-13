import assert from 'node:assert/strict';
import type { ReactNode } from 'react';
import { afterEach, test, vi } from 'vite-plus/test';
import type { Commit } from '../../resources/js/types';
import { find, hookHarness } from './HookHarness';

const harness = hookHarness();
const effects: (() => void)[] = [];
const subscriptions = new Set<{ element: HTMLElement }>();
const mutations = { prepare: () => null };
const polling = {
    mutations,
    subscribe: (subscription: { element: HTMLElement }) => {
        subscriptions.add(subscription);

        return () => subscriptions.delete(subscription);
    },
};
vi.doMock('react', async () => ({
    ...(await vi.importActual('react')),
    ...harness.react,
    useContext: () => polling,
    useEffect: (effect: () => void | (() => void), deps: unknown[]) =>
        harness.react.useEffect(() => {
            let cleanup: void | (() => void);
            effects.push(() => {
                cleanup = effect();
            });

            return () => {
                cleanup?.();
            };
        }, deps),
}));
vi.doMock('@inertiajs/react', async () => ({
    ...(await vi.importActual('@inertiajs/react')),
    usePage: () => ({
        props: { auth: { user: { id: 'viewer' }, organizations: [] } },
    }),
    useHttp: () => ({ processing: false }),
}));

const { CommitStarButton } =
    await import('../../resources/js/components/commit-star-button');
const commit = {
    id: 'replacement',
    author: { id: 'author' },
    status: 'approved',
    starred: false,
    stars_count: 0,
} as Commit;
const render = () => harness.render(CommitStarButton, { commit });
const committed = () => effects.splice(0).forEach((effect) => effect());
const attach = (tree: ReactNode, element: HTMLElement | null) => {
    const button = find(
        tree,
        (props) => props['data-test'] === 'star-commit-button',
    );
    assert.ok(button);
    (button.props.ref as (node: HTMLElement | null) => void)(element);
};

afterEach(() => {
    harness.unmount();
    subscriptions.clear();
    effects.length = 0;
});

test('replacing a tooltip trigger transfers live card registration to the new button', () => {
    const first = { name: 'first' } as unknown as HTMLElement;
    const replacement = { name: 'replacement' } as unknown as HTMLElement;
    attach(render(), first);
    committed();
    void render();
    committed();
    assert.deepEqual(
        [...subscriptions].map((s) => s.element),
        [first],
    );

    attach(render(), null);
    attach(render(), replacement);
    void render();
    committed();

    assert.deepEqual(
        [...subscriptions].map((s) => s.element),
        [replacement],
    );
});

test('a detached button stops keeping a card registered', () => {
    attach(render(), {} as HTMLElement);
    committed();
    void render();
    committed();
    attach(render(), null);
    void render();
    committed();

    assert.equal(subscriptions.size, 0);
});
