/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { ShownImage } from '../../resources/js/components/commit-images';

let state: unknown[] = [];
let cursor = 0;

type EffectEntry = {
    deps?: unknown[];
    cleanup?: void | (() => void);
};

let effectEntries: EffectEntry[] = [];
let effectCursor = 0;

function runCleanups() {
    effectEntries.forEach((entry) => {
        if (typeof entry.cleanup === 'function') {
            entry.cleanup();
        }
    });
    effectEntries = [];
    effectCursor = 0;
}

vi.doMock('react', () => ({
    ...React,
    useId: () => 'preview-test-id',
    useRef: (initial: unknown) => ({ current: initial }),
    useMemo: (factory: () => unknown) => factory(),
    useCallback: (fn: unknown) => fn,
    useEffect: (effect: () => void | (() => void), deps?: unknown[]) => {
        const index = effectCursor++;
        const prev = effectEntries[index];
        const changed =
            !prev ||
            !prev.deps ||
            !deps ||
            deps.length !== prev.deps.length ||
            deps.some((d, i) => d !== prev.deps![i]);

        if (changed) {
            if (typeof prev?.cleanup === 'function') {
                prev.cleanup();
            }
            effectEntries[index] = {
                deps: deps ? [...deps] : undefined,
                cleanup: effect(),
            };
        }
    },
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) {
            state[index] =
                typeof initial === 'function'
                    ? (initial as () => unknown)()
                    : initial;
        }

        return [
            state[index],
            (next: unknown) => {
                state[index] =
                    typeof next === 'function'
                        ? (next as (current: unknown) => unknown)(state[index])
                        : next;
            },
        ];
    },
}));

const { ImagePickerPreviews } = await import('../../resources/js/components/commit-image-picker');
const { ImageViewer } = await import('../../resources/js/components/image-viewer');

function elements(node: ReactNode): ReactElement<Record<string, unknown>>[] {
    if (!React.isValidElement(node)) {
        return [];
    }

    const element = node as ReactElement<Record<string, unknown>>;

    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(elements),
    ];
}

function renderTree(node: ReactNode): ReactElement<Record<string, unknown>>[] {
    cursor = 0;
    effectCursor = 0;
    return elements(node);
}

beforeEach(() => {
    state = [];
    cursor = 0;
    effectCursor = 0;
    effectEntries = [];
});

afterEach(() => {
    runCleanups();
    vi.unstubAllGlobals();
});

test('renders preview buttons for draft images and opens them in the image viewer when clicked', () => {
    const file1 = new File(['image-1'], 'photo1.png', { type: 'image/png' });
    const file2 = new File(['image-2'], 'photo2.jpg', { type: 'image/jpeg' });
    const images = [file1, file2];
    const descriptions = ['First caption', ''];
    const removed: number[] = [];

    // Initial render: no image is being previewed in lightbox
    let tree = renderTree(
        ImagePickerPreviews({
            images,
            descriptions,
            onDescribe: () => {},
            onRemove: (index) => removed.push(index),
        }),
    );

    const previewButtons = tree.filter((el) => el.props['data-test'] === 'preview-image-button');
    assert.equal(previewButtons.length, 2);
    assert.equal(previewButtons[0].props['aria-label'], 'Preview attached image 1');
    assert.equal(previewButtons[0].props.id, 'preview-test-id-preview-0');
    assert.equal(previewButtons[1].props['aria-label'], 'Preview attached image 2');
    assert.equal(previewButtons[1].props.id, 'preview-test-id-preview-1');

    let viewer = tree.find((el) => el.type === ImageViewer);
    assert.ok(viewer);
    assert.equal(viewer.props.index, null);
    const shownImages = viewer.props.images as ShownImage[];
    assert.equal(shownImages.length, 2);
    assert.equal(shownImages[0].id, 'photo1');
    assert.equal(shownImages[0].description, 'First caption');
    assert.equal(shownImages[1].id, 'photo2');
    assert.equal(shownImages[1].description, null);

    // Click first image to preview it
    (previewButtons[0].props.onClick as () => void)();

    // Re-render after state change
    tree = renderTree(
        ImagePickerPreviews({
            images,
            descriptions,
            onDescribe: () => {},
            onRemove: (index) => removed.push(index),
        }),
    );

    viewer = tree.find((el) => el.type === ImageViewer);
    assert.ok(viewer);
    assert.equal(viewer.props.index, 0);

    // Navigate to second image via ImageViewer
    (viewer.props.onView as (index: number) => void)(1);

    tree = renderTree(
        ImagePickerPreviews({
            images,
            descriptions,
            onDescribe: () => {},
            onRemove: (index) => removed.push(index),
        }),
    );

    viewer = tree.find((el) => el.type === ImageViewer);
    assert.ok(viewer);
    assert.equal(viewer.props.index, 1);

    // Close the viewer
    (viewer.props.onClose as () => void)();

    tree = renderTree(
        ImagePickerPreviews({
            images,
            descriptions,
            onDescribe: () => {},
            onRemove: (index) => removed.push(index),
        }),
    );

    viewer = tree.find((el) => el.type === ImageViewer);
    assert.ok(viewer);
    assert.equal(viewer.props.index, null);
});

test('removing the currently previewed image closes the viewer', () => {
    const file1 = new File(['image-1'], 'first.png', { type: 'image/png' });
    const file2 = new File(['image-2'], 'second.png', { type: 'image/png' });
    const images = [file1, file2];
    let removedIndex: number | null = null;

    let tree = renderTree(
        ImagePickerPreviews({
            images,
            descriptions: ['', ''],
            onDescribe: () => {},
            onRemove: (index) => {
                removedIndex = index;
            },
        }),
    );

    // Preview the first image
    const previewButtons = tree.filter((el) => el.props['data-test'] === 'preview-image-button');
    (previewButtons[0].props.onClick as () => void)();

    // Verify it is open at index 0
    tree = renderTree(
        ImagePickerPreviews({
            images,
            descriptions: ['', ''],
            onDescribe: () => {},
            onRemove: (index) => {
                removedIndex = index;
            },
        }),
    );
    let viewer = tree.find((el) => el.type === ImageViewer);
    assert.equal(viewer?.props.index, 0);

    // Click remove button on the first image
    const removeButtons = tree.filter((el) => typeof el.props['aria-label'] === 'string' && el.props['aria-label'].startsWith('Remove image'));
    (removeButtons[0].props.onClick as () => void)();
    assert.equal(removedIndex, 0);

    // Re-render: viewer should be closed (index null)
    tree = renderTree(
        ImagePickerPreviews({
            images: [file2],
            descriptions: [''],
            onDescribe: () => {},
            onRemove: () => {},
        }),
    );
    viewer = tree.find((el) => el.type === ImageViewer);
    assert.equal(viewer?.props.index, null);
});

test('removing an image preceding the currently previewed image shifts the index down', () => {
    const file1 = new File(['1'], '1.png', { type: 'image/png' });
    const file2 = new File(['2'], '2.png', { type: 'image/png' });
    const file3 = new File(['3'], '3.png', { type: 'image/png' });
    const images = [file1, file2, file3];

    let tree = renderTree(
        ImagePickerPreviews({
            images,
            descriptions: ['', '', ''],
            onDescribe: () => {},
            onRemove: () => {},
        }),
    );

    // Preview image 3 (index 2)
    const previewButtons = tree.filter((el) => el.props['data-test'] === 'preview-image-button');
    (previewButtons[2].props.onClick as () => void)();

    tree = renderTree(
        ImagePickerPreviews({
            images,
            descriptions: ['', '', ''],
            onDescribe: () => {},
            onRemove: () => {},
        }),
    );
    let viewer = tree.find((el) => el.type === ImageViewer);
    assert.equal(viewer?.props.index, 2);

    // Remove image 1 (index 0)
    const removeButtons = tree.filter((el) => typeof el.props['aria-label'] === 'string' && el.props['aria-label'].startsWith('Remove image'));
    (removeButtons[0].props.onClick as () => void)();

    // Re-render: viewer index should now be shifted to 1
    tree = renderTree(
        ImagePickerPreviews({
            images: [file2, file3],
            descriptions: ['', ''],
            onDescribe: () => {},
            onRemove: () => {},
        }),
    );
    viewer = tree.find((el) => el.type === ImageViewer);
    assert.equal(viewer?.props.index, 1);
});

test('intercepts Escape in capture phase while open, closing the viewer and returning focus to the draft without bubbling to dialogs', () => {
    const file = new File(['data'], 'shot.png', { type: 'image/png' });
    const focusedElements: string[] = [];
    const elementsById = new Map<string, { focus: () => void }>();

    elementsById.set('preview-test-id-preview-0', {
        focus: () => focusedElements.push('preview-test-id-preview-0'),
    });

    vi.stubGlobal('document', {
        getElementById: (targetId: string) => elementsById.get(targetId) ?? null,
    });

    const windowListeners: Record<string, ((event: unknown) => void)[]> = {};
    vi.stubGlobal('window', {
        addEventListener: (event: string, listener: (e: unknown) => void) => {
            windowListeners[event] = windowListeners[event] || [];
            windowListeners[event].push(listener);
        },
        removeEventListener: (event: string, listener: (e: unknown) => void) => {
            if (windowListeners[event]) {
                windowListeners[event] = windowListeners[event].filter((l) => l !== listener);
            }
        },
    });

    // 1. Initial render (viewer closed) -> no keydown capture listener registered
    renderTree(
        ImagePickerPreviews({
            images: [file],
            descriptions: [''],
            onDescribe: () => {},
            onRemove: () => {},
        }),
    );
    assert.equal(windowListeners['keydown']?.length ?? 0, 0);

    // 2. Open viewer at index 0 by clicking preview button
    let tree = renderTree(
        ImagePickerPreviews({
            images: [file],
            descriptions: [''],
            onDescribe: () => {},
            onRemove: () => {},
        }),
    );
    const previewBtn = tree.find((el) => el.props['data-test'] === 'preview-image-button')!;
    (previewBtn.props.onClick as () => void)();

    // Re-render open viewer
    tree = renderTree(
        ImagePickerPreviews({
            images: [file],
            descriptions: [''],
            onDescribe: () => {},
            onRemove: () => {},
        }),
    );
    assert.equal(windowListeners['keydown']?.length, 1);

    // 3. Fire Escape keydown event (simulating user pressing Escape while lightbox is open)
    let defaultPrevented = false;
    let propagationStopped = false;
    const escapeEvent = {
        key: 'Escape',
        preventDefault: () => {
            defaultPrevented = true;
        },
        stopPropagation: () => {
            propagationStopped = true;
        },
    };

    windowListeners['keydown'][0](escapeEvent);

    assert.equal(defaultPrevented, true);
    assert.equal(propagationStopped, true);
    assert.deepEqual(focusedElements, ['preview-test-id-preview-0']);

    // Re-render: viewer is now closed
    tree = renderTree(
        ImagePickerPreviews({
            images: [file],
            descriptions: [''],
            onDescribe: () => {},
            onRemove: () => {},
        }),
    );
    const viewer = tree.find((el) => el.type === ImageViewer)!;
    assert.equal(viewer.props.index, null);
    assert.equal(windowListeners['keydown']?.length ?? 0, 0);
});

test('preserves the original file format and filename when downloading draft images', () => {
    const png = new File(['png-data'], 'shot.png', { type: 'image/png' });
    const jpeg = new File(['jpeg-data'], 'photo.jpeg', { type: 'image/jpeg' });
    const noExtWebp = new File(['webp-data'], 'avatar', { type: 'image/webp' });
    const emptyNamePng = new File(['data'], '', { type: 'image/png' });

    const tree = renderTree(
        ImagePickerPreviews({
            images: [png, jpeg, noExtWebp, emptyNamePng],
            descriptions: ['', '', '', ''],
            onDescribe: () => {},
            onRemove: () => {},
        }),
    );

    const viewer = tree.find((el) => el.type === ImageViewer)!;
    assert.ok(viewer);
    const downloadFilename = viewer.props.downloadFilename as (image: ShownImage, index: number) => string;
    assert.ok(downloadFilename);

    assert.equal(downloadFilename({ id: 'shot', url: 'blob:1', description: null }, 0), 'shot.png');
    assert.equal(downloadFilename({ id: 'photo', url: 'blob:2', description: null }, 1), 'photo.jpeg');
    assert.equal(downloadFilename({ id: 'avatar', url: 'blob:3', description: null }, 2), 'avatar.webp');
    assert.equal(downloadFilename({ id: 'image-4', url: 'blob:4', description: null }, 3), 'image-4.png');
});

test('ImageViewer passes original filenames to slides and includes Download plugin when downloadUrl is provided', () => {
    const images: ShownImage[] = [
        { id: '1', url: 'blob:1', description: null },
        { id: '2', url: 'blob:2', description: null },
    ];

    const tree = renderTree(
        ImageViewer({
            images,
            index: 0,
            onView: () => {},
            onClose: () => {},
            downloadUrl: (image) => image.url,
            downloadFilename: (_image, index) => (index === 0 ? 'original-shot.png' : 'original-shot.jpg'),
        }),
    );

    const lightbox = tree.find((el) => 'slides' in el.props)!;
    const slides = lightbox.props.slides as Array<{ download?: { url: string; filename: string } }>;
    assert.equal(slides[0].download?.filename, 'original-shot.png');
    assert.equal(slides[0].download?.url, 'blob:1');
    assert.equal(slides[1].download?.filename, 'original-shot.jpg');
    assert.equal(slides[1].download?.url, 'blob:2');
});
