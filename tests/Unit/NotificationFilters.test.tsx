/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';
import { DropdownMenuCheckboxItem } from '../../resources/js/components/ui/dropdown-menu';

vi.doMock('@/hooks/use-mobile', () => ({ useIsMobile: () => true }));
const { NotificationFilters } =
    await import('../../resources/js/components/notification-filters');

type Element = ReactElement<Record<string, unknown>>;
const options = [
    'Replies',
    'Quotes',
    'Stars',
    'Mentions',
    'Follows',
    'Moderation',
].map((label) => ({ type: label.toLowerCase(), label }));
const all = options.map((option) => option.type);

function elements(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) return [];
    const element = node as Element;
    return [
        element,
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            elements,
        ),
    ];
}

function render(enabled: string[], onChange: (types: string[]) => void) {
    return elements(NotificationFilters({ options, enabled, onChange }));
}

test('selects additional filters without dismissing the menu and reflects the next filter response', () => {
    const onChange = vi.fn();
    const tree = render(all, onChange);
    const more = tree.find(
        (element) =>
            element.props['aria-label'] === 'More notification filters',
    );
    assert.ok(more);
    const choices = tree.filter(
        (element) => element.type === DropdownMenuCheckboxItem,
    );
    assert.deepEqual(
        choices.map((choice) => choice.props.children),
        ['Mentions', 'Follows', 'Moderation'],
    );
    assert.ok(choices.every((choice) => choice.props.checked === true));

    const event = { preventDefault: vi.fn() };
    (choices[0].props.onSelect as (event: unknown) => void)(event);
    (choices[0].props.onCheckedChange as (checked: boolean) => void)(false);
    assert.equal(event.preventDefault.mock.calls.length, 1);
    const remaining = all.filter((type) => type !== 'mentions');
    assert.deepEqual(onChange.mock.calls, [[remaining]]);

    const updated = render(remaining, onChange).filter(
        (element) => element.type === DropdownMenuCheckboxItem,
    );
    assert.equal(updated[0].props.checked, false);
    (updated[1].props.onCheckedChange as (checked: boolean) => void)(false);
    assert.deepEqual(onChange.mock.calls[1], [
        remaining.filter((type) => type !== 'follows'),
    ]);
});

test('primary filters add or remove only their own category', () => {
    const onChange = vi.fn();
    const button = render(all, onChange).find(
        (element) =>
            element.type === 'button' && element.props.children === 'Replies',
    );
    assert.ok(button);
    (button.props.onClick as () => void)();
    const remaining = all.filter((type) => type !== 'replies');
    assert.deepEqual(onChange.mock.calls, [[remaining]]);
    const disabled = render(remaining, onChange).find(
        (element) =>
            element.type === 'button' && element.props.children === 'Replies',
    );
    assert.ok(disabled);
    assert.equal(disabled.props['aria-pressed'], false);
    (disabled.props.onClick as () => void)();
    assert.deepEqual(onChange.mock.calls[1], [[...remaining, 'replies']]);
});
