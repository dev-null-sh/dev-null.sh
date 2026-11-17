/// <reference types="node" />
import assert from 'node:assert/strict';
import type { Ref } from 'react';
import { act, createRef, useImperativeHandle, useState } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, test, vi } from 'vite-plus/test';
import { useLeaving } from '../../resources/js/hooks/use-leaving';

// React DOM reads `window.event` to pick an update's priority; nothing else of the DOM is needed here.
vi.stubGlobal('window', globalThis);
vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);

const roots: Root[] = [];

afterEach(() => {
    act(() => roots.splice(0).forEach((root) => root.unmount()));
});

function container(): Element {
    const view = {
        document: { activeElement: null },
        HTMLIFrameElement: class {},
    };
    const node = {
        nodeType: 1,
        nodeName: 'DIV',
        tagName: 'DIV',
        addEventListener: () => {},
        removeEventListener: () => {},
    };

    return {
        ...node,
        ownerDocument: { ...node, defaultView: view },
    } as unknown as Element;
}

type Handle = ReturnType<typeof useLeaving> & {
    show: (isShown: boolean) => void;
    reduceMotion: (isStill: boolean) => void;
};

function mount(isShown: boolean, isStill = false) {
    const handle = createRef<Handle>();
    const motion = Object.assign(new EventTarget(), { matches: isStill });
    vi.stubGlobal('matchMedia', () => motion);

    function Leaving({ handleRef }: { handleRef: Ref<Handle> }) {
        const [shown, show] = useState(isShown);
        const leaving = useLeaving(shown);

        useImperativeHandle(handleRef, () => ({
            ...leaving,
            show,
            reduceMotion: (isStill) => {
                motion.matches = isStill;
                motion.dispatchEvent(new Event('change'));
            },
        }));

        return null;
    }

    const root = createRoot(container());
    roots.push(root);
    act(() => root.render(<Leaving handleRef={handle} />));

    return handle;
}

test('renders what is shown, without leaving', () => {
    const handle = mount(true);

    assert.equal(handle.current?.isRendered, true);
    assert.equal(handle.current?.isLeaving, false);
});

test('renders nothing that was never shown', () => {
    const handle = mount(false);

    assert.equal(handle.current?.isRendered, false);
    assert.equal(handle.current?.isLeaving, false);
});

test('keeps rendering what gets hidden while it leaves, until it has left', () => {
    const handle = mount(true);

    act(() => handle.current?.show(false));

    assert.equal(handle.current?.isRendered, true);
    assert.equal(handle.current?.isLeaving, true);

    act(() => handle.current?.onLeft());

    assert.equal(handle.current?.isRendered, false);
    assert.equal(handle.current?.isLeaving, false);
});

test('stops leaving when shown again before it has left', () => {
    const handle = mount(true);

    act(() => handle.current?.show(false));
    act(() => handle.current?.show(true));

    assert.equal(handle.current?.isRendered, true);
    assert.equal(handle.current?.isLeaving, false);
});

test('drops what gets hidden right away without motion, as nothing would end its leaving', () => {
    const handle = mount(true, true);

    act(() => handle.current?.show(false));

    assert.equal(handle.current?.isRendered, false);
    assert.equal(handle.current?.isLeaving, false);
});

test('finishes leaving when reduced motion cancels the animation', () => {
    const handle = mount(true);

    act(() => handle.current?.show(false));
    assert.equal(handle.current?.isLeaving, true);

    act(() => handle.current?.reduceMotion(true));

    assert.equal(handle.current?.isRendered, false);
    assert.equal(handle.current?.isLeaving, false);

    act(() => handle.current?.reduceMotion(false));
    assert.equal(handle.current?.isRendered, false);
});

test('keeps reopened content visible when reduced motion changes', () => {
    const handle = mount(true);

    act(() => handle.current?.show(false));
    act(() => handle.current?.show(true));
    act(() => handle.current?.reduceMotion(true));

    assert.equal(handle.current?.isRendered, true);
    assert.equal(handle.current?.isLeaving, false);
});
