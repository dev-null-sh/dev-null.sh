/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import type { ReactElement, SyntheticEvent } from 'react';
import { CommitImages } from '../../resources/js/components/commit-images';

function renderedImage() {
    const gallery = CommitImages({
        images: [
            { id: 1, url: '/images/missing.webp', width: 100, height: 100 },
        ],
    });
    const link = gallery!.props.children[0];

    return link.props.children as ReactElement<{
        src: string;
        onError: (event: SyntheticEvent<HTMLImageElement>) => void;
    }>;
}

void test('keeps the uploaded image until it fails to load', () => {
    assert.equal(renderedImage().props.src, '/images/missing.webp');
});

void test('replaces a failed image with the site logo without retrying the fallback', () => {
    let replacements = 0;
    let source = '/images/missing.webp';
    const target = {
        getAttribute: () => source,
        set src(value: string) {
            source = value;
            replacements++;
        },
        classList: {
            replace: (from: string, to: string) => {
                assert.equal(from, 'object-cover');
                assert.equal(to, 'object-contain');
            },
        },
    };
    const event = {
        currentTarget: target,
    } as unknown as SyntheticEvent<HTMLImageElement>;
    const { onError } = renderedImage().props;

    onError(event);
    assert.equal(source, '/favicon.svg');

    onError(event);
    assert.equal(replacements, 1);
});

void test('renders nothing when there are no uploaded images', () => {
    assert.equal(CommitImages({ images: [] }), null);
});
