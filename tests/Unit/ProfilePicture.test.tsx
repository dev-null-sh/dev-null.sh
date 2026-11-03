/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement } from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';

let index: number | null = null;

vi.doMock('react', () => ({
    ...React,
    useState: () => [index, (next: number | null) => (index = next)],
    useMemo: (factory: () => unknown) => factory(),
    useEffect: () => {},
    useRef: () => ({ current: null }),
}));

const { ProfilePicture } =
    await import('../../resources/js/components/profile-picture');
const { ImageViewer } =
    await import('../../resources/js/components/image-viewer');
const { UserAvatar } =
    await import('../../resources/js/components/user-avatar');

const user = {
    id: 'profile-id',
    name: 'Octocat',
    avatar_url: 'https://avatars.githubusercontent.com/u/1?v=4&s=80',
};

beforeEach(() => {
    index = null;
});

test('opens the full-size profile picture and closes it through the shared viewer', () => {
    const children = () =>
        React.Children.toArray(
            ProfilePicture({ user }).props.children,
        ) as ReactElement[];

    let [trigger, viewer] = children() as [
        ReactElement<React.ComponentProps<'button'>>,
        ReactElement<React.ComponentProps<typeof ImageViewer>>,
    ];
    assert.equal(trigger.type, 'button');
    assert.equal(trigger.props['aria-label'], "View Octocat's profile picture");
    assert.equal(trigger.props['aria-haspopup'], 'dialog');
    assert.equal(viewer.type, ImageViewer);
    assert.equal(viewer.props.index, null);

    trigger.props.onClick!({} as React.MouseEvent<HTMLButtonElement>);
    [trigger, viewer] = children() as [typeof trigger, typeof viewer];

    assert.equal(viewer.props.index, 0);
    assert.equal(
        viewer.props.images[0].url,
        'https://avatars.githubusercontent.com/u/1?v=4&s=1024',
    );
    assert.equal(viewer.props.images[0].alt, "Octocat's profile picture");
    assert.equal(viewer.props.images[0].description, null);

    const lightbox = ImageViewer(viewer.props);
    assert.deepEqual(lightbox.props.toolbar.buttons, ['close']);
    assert.equal(lightbox.props.render.buttonZoom?.({} as never), null);
    assert.equal(lightbox.props.render.buttonPrev?.(), null);
    assert.equal(lightbox.props.render.buttonNext?.(), null);
    assert.equal(
        lightbox.props.render.slideFooter?.({
            slide: lightbox.props.slides[0],
        } as never),
        null,
    );
    assert.equal(lightbox.props.slides[0].alt, "Octocat's profile picture");
    assert.equal(
        lightbox.props.styles.container.backgroundColor,
        'rgb(0 0 0 / 0.85)',
    );

    viewer.props.onClose();
    assert.equal(
        (children()[1].props as React.ComponentProps<typeof ImageViewer>).index,
        null,
    );
});

test('keeps initials as a static avatar when there is no picture to open', () => {
    const avatar = ProfilePicture({ user: { ...user, avatar_url: null } });

    assert.equal(avatar.type, UserAvatar);
});

test('retains zoom controls and image descriptions for existing image viewers', () => {
    const lightbox = ImageViewer({
        images: [
            {
                id: 'image',
                url: '/image.webp',
                description: 'Image description',
            },
        ],
        index: 0,
        onView: () => {},
        onClose: () => {},
    });

    assert.equal(lightbox.props.render.buttonZoom, undefined);
    assert.equal(lightbox.props.slides[0].alt, 'Image description');
    assert.notEqual(
        lightbox.props.render.slideFooter?.({
            slide: lightbox.props.slides[0],
        } as never),
        null,
    );
});
