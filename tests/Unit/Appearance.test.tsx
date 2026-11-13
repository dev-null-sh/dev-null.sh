/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { beforeEach, test, vi } from 'vite-plus/test';

const rendered = vi.fn();
vi.doMock('react', () => ({
    ...React,
    useSyncExternalStore: (
        subscribe: (listener: () => void) => () => void,
        snapshot: () => unknown,
    ) => {
        subscribe(rendered);
        return snapshot();
    },
}));

const saved = new Map<string, string>();
const storageListeners: ((event: { key: string | null }) => void)[] = [];
const systemThemeListeners: (() => void)[] = [];
let systemIsDark = false;
const classes = new Set<string>();

const localStorage = {
    getItem: (key: string) => saved.get(key) ?? null,
    setItem: (key: string, value: string) => saved.set(key, value),
    removeItem: (key: string) => saved.delete(key),
};

Object.assign(globalThis, {
    localStorage,
    window: {
        localStorage,
        matchMedia: () => ({
            get matches() {
                return systemIsDark;
            },
            addEventListener: (_type: string, listener: () => void) =>
                systemThemeListeners.push(listener),
        }),
        addEventListener: (
            type: string,
            listener: (event: { key: string | null }) => void,
        ) => {
            if (type === 'storage') {
                storageListeners.push(listener);
            }
        },
    },
    document: {
        cookie: '',
        documentElement: {
            classList: {
                toggle: (name: string, on: boolean) =>
                    on ? classes.add(name) : classes.delete(name),
            },
            style: {},
        },
        querySelectorAll: () => [],
    },
    getComputedStyle: () => ({ getPropertyValue: () => '' }),
});

const { initializeTheme, useAppearance } =
    await import('../../resources/js/hooks/use-appearance');

saved.set('appearance', 'light');
initializeTheme();

beforeEach(() => {
    rendered.mockClear();
});

test('follows a theme another tab saves', () => {
    useAppearance();
    saved.set('appearance', 'dark');
    storageListeners.forEach((listener) => listener({ key: 'appearance' }));

    assert.ok(classes.has('dark'));
    assert.equal(useAppearance().appearance, 'dark');
    assert.ok(rendered.mock.calls.length > 0);
});

test('falls back to the system theme when another tab wipes local storage', () => {
    systemIsDark = false;
    saved.clear();
    storageListeners.forEach((listener) => listener({ key: null }));

    assert.ok(!classes.has('dark'));
    assert.equal(useAppearance().appearance, 'system');
});

test('ignores what other tabs save under other keys', () => {
    saved.set('appearance', 'dark');
    storageListeners.forEach((listener) => listener({ key: 'chat-dock' }));

    assert.equal(useAppearance().appearance, 'system');
    assert.equal(rendered.mock.calls.length, 0);
});

test('re-renders the resolved theme when the system one changes', () => {
    saved.set('appearance', 'system');
    storageListeners.forEach((listener) => listener({ key: 'appearance' }));
    useAppearance();
    rendered.mockClear();

    systemIsDark = true;
    systemThemeListeners.forEach((listener) => listener());

    assert.ok(classes.has('dark'));
    assert.ok(rendered.mock.calls.length > 0);
    assert.equal(useAppearance().resolvedAppearance, 'dark');
});
