/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { App } from '@inertiajs/react';
import type { ComponentProps, KeyboardEvent } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { CommitComposer } from '../../resources/js/components/commit-composer';
import {
    ComposerPreviewBody,
    useComposerPreview,
    type ComposerPreview,
} from '../../resources/js/components/composer-preview';

function previewFor(options: {
    body?: string;
    isSubmittable: boolean;
    onAddPart?: () => void;
}): ComposerPreview {
    const previews: ComposerPreview[] = [];

    function Composer() {
        previews.push(
            useComposerPreview({
                body: 'Draft commit',
                textareaRef: { current: null },
                ...options,
            }),
        );

        return null;
    }

    renderToStaticMarkup(<Composer />);
    const preview = previews[0];
    assert.ok(preview);

    return preview;
}

for (const modifier of ['ctrlKey', 'metaKey'] as const) {
    for (const shiftKey of [false, true]) {
        void test(`${modifier} + ${shiftKey ? 'Shift + ' : ''}Enter ${shiftKey ? 'adds a series part' : 'publishes'} from preview`, () => {
            let added = 0;
            let submitted = 0;
            let prevented = 0;
            const preview = previewFor({
                isSubmittable: true,
                onAddPart: () => added++,
            });

            preview.onPreviewKeyDown({
                key: 'Enter',
                [modifier]: true,
                shiftKey,
                preventDefault: () => prevented++,
                currentTarget: {
                    closest: () => ({ requestSubmit: () => submitted++ }),
                },
            } as unknown as KeyboardEvent<HTMLDivElement>);

            assert.equal(added, shiftKey ? 1 : 0);
            assert.equal(submitted, shiftKey ? 0 : 1);
            assert.equal(prevented, 1);
        });
    }
}

void test('does not publish an incomplete series from preview', () => {
    previewFor({ isSubmittable: false }).onPreviewKeyDown({
        key: 'Enter',
        ctrlKey: true,
        shiftKey: false,
        preventDefault: () => assert.fail('must not submit'),
    } as unknown as KeyboardEvent<HTMLDivElement>);
});

void test('does not publish a reply with the add-part shortcut', () => {
    previewFor({ isSubmittable: true }).onPreviewKeyDown({
        key: 'Enter',
        ctrlKey: true,
        shiftKey: true,
        preventDefault: () => assert.fail('must not submit'),
    } as unknown as KeyboardEvent<HTMLDivElement>);
});

function renderComposer(bodies: string[]): string {
    const windowDescriptor = Object.getOwnPropertyDescriptor(
        globalThis,
        'window',
    );
    const draft = new Map([
        ['stash:preview-user:commit', bodies[0]],
        ['stash:preview-user:commit:later', JSON.stringify(bodies.slice(1))],
    ]);

    function ComposerPage() {
        Object.defineProperty(globalThis, 'window', {
            configurable: true,
            value: {
                localStorage: {
                    getItem: (key: string) => draft.get(key) ?? null,
                },
            },
        });

        return <CommitComposer />;
    }

    try {
        return renderToStaticMarkup(
            <App
                initialComponent={ComposerPage}
                initialPage={
                    {
                        component: 'Composer',
                        url: '/',
                        version: null,
                        props: {
                            errors: {},
                            auth: {
                                user: {
                                    id: 'preview-user',
                                    name: 'Preview User',
                                    lane: 0,
                                },
                                log: {},
                            },
                        },
                        clearHistory: false,
                        encryptHistory: false,
                    } as unknown as ComponentProps<typeof App>['initialPage']
                }
            />,
        );
    } finally {
        if (windowDescriptor) {
            Object.defineProperty(globalThis, 'window', windowDescriptor);
        } else {
            Reflect.deleteProperty(globalThis, 'window');
        }
    }
}

for (const count of [1, 2, 3, 280]) {
    void test(`counts ${count} emoji as Unicode characters in the preview composer`, () => {
        const markup = renderComposer(['🚀'.repeat(count)]);
        const submit = markup.match(
            /<button[^>]*data-test="create-commit-button"[^>]*>/,
        )?.[0];

        assert.ok(submit);
        assert.equal(/\sdisabled(?:=|\s|>)/.test(submit), count < 3);
        assert.ok(
            markup.includes(`aria-label="${280 - count} characters left"`),
        );
        assert.ok(markup.includes('Preview'));
    });
}

void test('requires every preview series part to contain three Unicode characters', () => {
    const markup = renderComposer(['🚀🚀🚀', '🚀🚀']);
    const submit = markup.match(
        /<button[^>]*data-test="create-commit-button"[^>]*>/,
    )?.[0];

    assert.ok(submit && /\sdisabled(?:=|\s|>)/.test(submit));
    assert.ok(markup.includes('aria-label="277 characters left"'));
    assert.ok(markup.includes('aria-label="278 characters left"'));
});

void test('previews the chosen link card instead of the first link in the body', () => {
    const preview = previewFor({
        body: 'https://first.example.com https://second.example.com',
        isSubmittable: true,
    });
    const markup = renderToStaticMarkup(
        <ComposerPreviewBody
            preview={preview}
            images={[]}
            url="https://second.example.com"
        />,
    );
    const card = markup.match(/data-test="link-card-preview"[\s\S]*/)?.[0];

    assert.ok(card);
    assert.ok(card.includes('second.example.com'));
    assert.ok(!card.includes('first.example.com'));
});

void test('omits the preview card when the author chooses no card', () => {
    const preview = previewFor({
        body: 'https://first.example.com',
        isSubmittable: true,
    });
    const markup = renderToStaticMarkup(
        <ComposerPreviewBody preview={preview} images={[]} url={null} />,
    );

    assert.ok(markup.includes('href="https://first.example.com"'));
    assert.ok(!markup.includes('data-test="link-card-preview"'));
});

void test('retains the link card picker beside preview for a draft and every series part', () => {
    for (const bodies of [
        ['https://first.example.com https://second.example.com'],
        ['https://first.example.com', 'https://second.example.com'],
    ]) {
        const markup = renderComposer(bodies);

        assert.equal(
            (markup.match(/data-test="link-card-button"/g) ?? []).length,
            bodies.length,
        );
        assert.equal(
            (markup.match(/data-test="preview-toggle"/g) ?? []).length,
            bodies.length,
        );
    }
});
