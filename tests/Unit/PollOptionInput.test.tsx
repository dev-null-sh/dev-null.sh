/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';

const { PollEditor, emptyPollDraft } =
    await import('../../resources/js/components/poll-editor');
const { MentionTextarea } =
    await import('../../resources/js/components/mention-textarea');

type Element = ReactElement<Record<string, unknown>>;

function elements(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) {
        return [];
    }
    const element = node as Element;
    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            elements,
        ),
    ];
}

function options(onChange = vi.fn()) {
    const editor = PollEditor({
        id: 'poll',
        poll: emptyPollDraft,
        errors: {},
        onChange,
        onRemove: vi.fn(),
    });
    return elements(editor).filter(
        (element) => element.type === MentionTextarea,
    );
}

test('suggests emoji but not mentions in each poll option', () => {
    const fields = options();

    assert.equal(fields.length, 2);
    for (const field of fields) {
        assert.equal(field.props.suggestsMentions, false);
        assert.equal(field.props.name, 'poll[options][]');
        assert.equal(field.props.maxLength, 50);
        assert.equal(field.props.rows, 1);
    }
});

test('keeps a poll option on one line', () => {
    const onChange = vi.fn();
    const [first] = options(onChange);

    (first.props.onValueChange as (value: string) => void)('Yes\nplease\r\n');
    assert.deepEqual(onChange.mock.calls[0][0].options, ['Yes please ', '']);

    const enter = { key: 'Enter', preventDefault: vi.fn() };
    (first.props.onKeyDown as (event: unknown) => void)(enter);
    assert.equal(enter.preventDefault.mock.calls.length, 1);

    const letter = { key: 'a', preventDefault: vi.fn() };
    (first.props.onKeyDown as (event: unknown) => void)(letter);
    assert.equal(letter.preventDefault.mock.calls.length, 0);
});
