/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';

const { PollEditor, emptyPollDraft, isPollOptionTooLong } =
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

function render(onChange = vi.fn(), poll = emptyPollDraft) {
    return elements(
        PollEditor({
            id: 'poll',
            poll,
            errors: {},
            onChange,
            onRemove: vi.fn(),
        }),
    );
}

function options(onChange = vi.fn(), poll = emptyPollDraft) {
    return render(onChange, poll).filter(
        (element) => element.type === MentionTextarea,
    );
}

test('suggests emoji but not mentions in each poll option', () => {
    const fields = options();

    assert.equal(fields.length, 2);
    for (const field of fields) {
        assert.equal(field.props.suggestsMentions, false);
        assert.equal(field.props.name, 'poll[options][]');
        assert.equal(field.props.maxLength, 2098);
        assert.equal(field.props.rows, 1);
    }
});

test('counts a link in a poll option as at most 23 characters, as in the body', () => {
    const link = 'https://github.com/dev-null-sh/dev-null.sh/pull/1366';
    const prose = `${'a'.repeat(30)} https://example.com/${'b'.repeat(40)}`;
    const fields = options(vi.fn(), {
        ...emptyPollDraft,
        options: [`Review ${link} first`, prose],
    });

    assert.equal(fields[0].props['aria-invalid'], undefined);
    assert.equal(fields[1].props['aria-invalid'], true);
    assert.ok(
        render(vi.fn(), {
            ...emptyPollDraft,
            options: ['Tabs', prose],
        }).some(
            (element) =>
                element.props.message ===
                'The option 2 field must not be greater than 50 characters.',
        ),
    );
    assert.equal(isPollOptionTooLong(`Review ${link} first`), false);
    assert.equal(isPollOptionTooLong(prose), true);
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
