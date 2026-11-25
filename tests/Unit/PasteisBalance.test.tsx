/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { beforeEach, test, vi } from 'vite-plus/test';

type Update = number | ((current: number) => number);

const day = '2026-10-16';
const values = new Map<string, number>();
let pasteis: { remaining: number; day: string } | null = null;

// Effects run during the render, so a test sees what the balance keeps in the shared allowance.
vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: () => void) => effect(),
    useEffectEvent: (callback: unknown) => callback,
}));
vi.doMock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { user: { id: 'viewer' }, pasteis } } }),
}));
vi.doMock('@/hooks/use-now', () => ({
    useNow: () => new Date(`${day}T12:00:00Z`),
}));
vi.doMock('@/hooks/use-shared-state', () => ({
    useSharedState: (key: string, initial: number) => [
        values.has(key) ? values.get(key) : initial,
        (update: Update) =>
            values.set(
                key,
                typeof update === 'function'
                    ? update(values.has(key) ? values.get(key)! : initial)
                    : update,
            ),
    ],
}));

const { awarding, PasteisBalance } =
    await import('../../resources/js/components/pasteis-balance');

const allowance = `pasteis-allowance:viewer:${day}`;

beforeEach(() => {
    values.clear();
    pasteis = { remaining: 7, day };
});

test('shows nothing to someone with pastéis mode off', () => {
    pasteis = null;

    assert.equal(renderToStaticMarkup(<PasteisBalance />), '');
});

test('shows what is left to give today', () => {
    const markup = renderToStaticMarkup(<PasteisBalance />);

    assert.ok(markup.includes('7/10 left today'));
    assert.ok(
        markup.includes(
            'title="7 of 10 pastéis left today, resets at 00:00 UTC"',
        ),
    );
});

test('goes down as soon as a pastel button spends one', () => {
    values.set(allowance, 6);

    assert.ok(renderToStaticMarkup(<PasteisBalance />).includes('6/10'));
});

test('takes the live count when pastéis went out from another device', () => {
    values.set(allowance, 7);
    pasteis = { remaining: 4, day };

    assert.ok(renderToStaticMarkup(<PasteisBalance />).includes('4/10'));
});

test('keeps a lower live count when props from an earlier visit come back', () => {
    values.set(allowance, 7);
    pasteis = { remaining: 4, day };
    renderToStaticMarkup(<PasteisBalance />);

    pasteis = { remaining: 7, day };

    assert.equal(values.get(allowance), 4);
    assert.ok(renderToStaticMarkup(<PasteisBalance />).includes('4/10'));
});

test('leaves the shared allowance alone while a pastel is being given', () => {
    values.set(allowance, 7);
    pasteis = { remaining: 4, day };
    awarding.add('viewer');

    try {
        assert.ok(renderToStaticMarkup(<PasteisBalance />).includes('4/10'));
        assert.equal(values.get(allowance), 7);
    } finally {
        awarding.delete('viewer');
    }
});

test('starts the day again at ten once the server count is from yesterday', () => {
    pasteis = { remaining: 0, day: '2026-10-15' };

    assert.ok(renderToStaticMarkup(<PasteisBalance />).includes('10/10'));
});

test('keeps the compact count readable for screen readers', () => {
    const markup = renderToStaticMarkup(<PasteisBalance isCompact />);

    assert.match(markup, />7<span class="sr-only"> pastéis left today<\/span>/);
    assert.ok(!markup.includes('/10 left today'));
});
