/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';
import type { ShownImage } from '../../resources/js/components/commit-images';

let state: unknown[] = [];
let cursor = 0;

vi.doMock('react', () => ({
    ...React,
    useId: () => 'preview-test-id',
    useRef: (initial: unknown) => ({ current: initial }),
    useState: (initial: unknown) => {
        const index = cursor++;
        if (!(index in state)) {
            state[index] = typeof initial === 'function' ? (initial as () => unknown)() : initial;
        }

        return [
            state[index],
            (next: unknown) => {
                state[index] = typeof next === 'function' ? (next as (current: unknown) => unknown)(state[index]) : next;
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

beforeEach(() => {
    state = [];
    cursor = 0;
});

test('renders preview buttons for draft images and opens them in the image viewer when clicked', () => {
    const file1 = new File(['image-1'], 'photo1.png', { type: 'image/png' });
    const file2 = new File(['image-2'], 'photo2.jpg', { type: 'image/jpeg' });
    const images = [file1, file2];
    const descriptions = ['First caption', ''];
    const removed: number[] = [];

    // Initial render: no image is being previewed in lightbox
    cursor = 0;
    let tree = elements(
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
    assert.equal(previewButtons[1].props['aria-label'], 'Preview attached image 2');

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
    cursor = 0;
    tree = elements(
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

    cursor = 0;
    tree = elements(
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

    cursor = 0;
    tree = elements(
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

    cursor = 0;
    let tree = elements(
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
    cursor = 0;
    tree = elements(
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
    cursor = 0;
    tree = elements(
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

    cursor = 0;
    let tree = elements(
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

    cursor = 0;
    tree = elements(
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
    cursor = 0;
    tree = elements(
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
