/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type {
    HTMLAttributes,
    PointerEvent,
    ReactElement,
    ReactNode,
} from 'react';
import { afterAll, test, vi } from 'vite-plus/test';
import type { User } from '../../resources/js/types';

let showPreview = false;

vi.doMock('react', () => ({
    ...React,
    useEffect: () => {},
    useLayoutEffect: () => {},
    useRef: (initial: unknown) => ({ current: initial }),
    useState: (initial: unknown) => [
        typeof initial === 'function'
            ? initial()
            : initial === null && showPreview
              ? { left: 0, top: 0 }
              : initial,
        () => {},
    ],
}));
vi.doMock('react-dom', async () => ({
    ...(await vi.importActual('react-dom')),
    createPortal: (children: ReactNode) => children,
}));

const { GraphRail } = await import('../../resources/js/components/graph-rail');
const { UserPreview } =
    await import('../../resources/js/components/user-preview');

vi.stubGlobal('window', { matchMedia: () => ({ matches: false }) });
vi.stubGlobal('document', { body: {} });
afterAll(() => vi.unstubAllGlobals());

const surfaces = {
    rail: (onHoverChange: (hovering: boolean) => void) =>
        GraphRail({ lane: 'red', onHoverChange }).props,
    'profile preview trigger': (onHoverChange: (hovering: boolean) => void) => {
        const preview = UserPreview({
            user: { username: 'author' },
            children: 'Author',
            onHoverChange,
        });

        return (
            React.Children.toArray(preview.props.children)[0] as ReactElement<
                HTMLAttributes<HTMLElement>
            >
        ).props;
    },
    'profile preview panel': (onHoverChange: (hovering: boolean) => void) => {
        showPreview = true;
        const preview = UserPreview({
            user: { id: 'author', username: 'author' } as User,
            children: 'Author',
            onHoverChange,
        });
        showPreview = false;

        return (
            React.Children.toArray(preview.props.children)[1] as ReactElement<
                HTMLAttributes<HTMLElement>
            >
        ).props;
    },
};

for (const [name, render] of Object.entries(surfaces)) {
    for (const pointerType of ['touch', 'pen']) {
        test(`${name} ignores ${pointerType} and compatibility mouse hover`, () => {
            const changes: boolean[] = [];
            const props = render((hovering) => changes.push(hovering));

            props.onPointerEnter?.({
                pointerType,
            } as PointerEvent<HTMLElement>);
            props.onPointerLeave?.({
                pointerType,
            } as PointerEvent<HTMLElement>);
            props.onMouseEnter?.({} as React.MouseEvent<HTMLElement>);

            assert.deepEqual(changes, []);
        });
    }

    test(`${name} highlights the author only while a mouse is over it`, () => {
        const changes: boolean[] = [];
        const props = render((hovering) => changes.push(hovering));

        props.onPointerEnter?.({
            pointerType: 'mouse',
        } as PointerEvent<HTMLElement>);
        props.onPointerLeave?.({
            pointerType: 'mouse',
        } as PointerEvent<HTMLElement>);

        assert.deepEqual(changes, [true, false]);
    });
}
