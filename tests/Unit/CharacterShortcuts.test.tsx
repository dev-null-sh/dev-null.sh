/// <reference types="node" />
import assert from 'node:assert/strict';
import { afterEach, beforeEach, test, vi } from 'vite-plus/test';
import type { Log, User } from '../../resources/js/types';

const effects: (() => void)[] = [];
const subscriptions: (() => void)[] = [];
const visit = vi.fn();
const compose = vi.fn();
const search = vi.fn();
const help = vi.fn();

vi.doMock('react', async () => ({
    ...(await vi.importActual('react')),
    useEffect: (effect: () => (() => void) | undefined) => {
        const cleanup = effect();
        if (cleanup) effects.push(cleanup);
    },
    useSyncExternalStore: (
        subscribe: (callback: () => void) => () => void,
        snapshot: () => boolean,
    ) => {
        subscriptions.push(subscribe(() => {}));
        return snapshot();
    },
}));
vi.doMock('@inertiajs/react', () => ({ router: { visit } }));
vi.doMock('@/lib/auth', () => ({ signIn: vi.fn() }));
vi.doMock('@/actions/App/Http/Controllers/LogController', () => ({
    default: { show: (log: Log) => `/log/${log}` },
}));
vi.doMock('@/routes/user', () => ({ show: () => '/@developer' }));
vi.doMock('@/routes/user-profile', () => ({ edit: () => '/settings/profile' }));

class Element {
    isContentEditable = false;
    tagName = 'DIV';
    dataset = { permalink: '/@developer/commit' };
    action = vi.fn();
    focus = vi.fn();
    scrollIntoView = vi.fn();
    matches = () => true;
    closest = () => this;
    contains = (element: unknown) => element === this;
    querySelector = (selector: string) =>
        ['r', 'q', 'o', 'y', 'a', 'x'].some((key) =>
            selector.endsWith(`="${key}"]`),
        )
            ? { click: this.action }
            : null;
}

const values = new Map<string, string>();
const browser = new EventTarget();
const commit = new Element();
let activeElement: Element | null = null;
let dialog = false;
let store: typeof import('../../resources/js/hooks/use-character-shortcuts');
let shortcuts: typeof import('../../resources/js/hooks/use-keyboard-shortcuts');

function key(
    value: string,
    options: {
        ctrlKey?: boolean;
        metaKey?: boolean;
        isComposing?: boolean;
        target?: Element;
        prevented?: boolean;
    } = {},
): Event {
    const { target, prevented, ...modifiers } = options;
    const event = Object.assign(new Event('keydown', { cancelable: true }), {
        key: value,
        metaKey: false,
        ctrlKey: false,
        altKey: false,
        isComposing: false,
        ...modifiers,
    });
    if (target) Object.defineProperty(event, 'target', { value: target });
    if (prevented) event.preventDefault();
    browser.dispatchEvent(event);
    return event;
}

function mount() {
    shortcuts.useKeyboardShortcuts(
        { id: 'developer' } as User,
        'following',
        help,
        search,
        compose,
    );
}

function storage(key: string | null) {
    browser.dispatchEvent(Object.assign(new Event('storage'), { key }));
}

beforeEach(async () => {
    vi.resetModules();
    vi.clearAllMocks();
    values.clear();
    activeElement = null;
    dialog = false;
    Object.defineProperty(browser, 'localStorage', {
        configurable: true,
        value: {
            getItem: (key: string) => values.get(key) ?? null,
            setItem: (key: string, value: string) => values.set(key, value),
        },
    });
    vi.stubGlobal('window', browser);
    vi.stubGlobal('HTMLElement', Element);
    vi.stubGlobal('CSS', { escape: (key: string) => key });
    vi.stubGlobal('document', {
        get activeElement() {
            return activeElement;
        },
        querySelector: () => (dialog ? {} : null),
        querySelectorAll: () => [commit],
    });
    store = await import('../../resources/js/hooks/use-character-shortcuts');
    shortcuts = await import('../../resources/js/hooks/use-keyboard-shortcuts');
});

afterEach(() => {
    effects.splice(0).forEach((cleanup) => cleanup());
    subscriptions.splice(0).forEach((cleanup) => cleanup());
    vi.unstubAllGlobals();
    vi.useRealTimers();
});

test('keeps existing character shortcuts enabled by default', () => {
    mount();
    for (const value of ['c', '/', '?', 'j', 'k'])
        assert(key(value).defaultPrevented);
    assert.equal(compose.mock.calls.length, 1);
    assert.equal(search.mock.calls.length, 1);
    assert.equal(help.mock.calls.length, 1);
    assert.equal(commit.focus.mock.calls.length, 2);
    key('g');
    key('p');
    assert.deepEqual(visit.mock.calls, [['/@developer']]);
});

test('reads a saved disabled preference before handling any character keys', () => {
    values.set('character-shortcuts', 'false');
    activeElement = commit;
    mount();
    for (const value of [
        'c',
        '/',
        '?',
        'j',
        'k',
        'g',
        'p',
        'r',
        'q',
        'o',
        'y',
        'a',
        'x',
    ]) {
        assert.equal(key(value).defaultPrevented, false, value);
    }
    assert.equal(
        compose.mock.calls.length +
            search.mock.calls.length +
            help.mock.calls.length +
            visit.mock.calls.length +
            commit.action.mock.calls.length +
            commit.focus.mock.calls.length,
        0,
    );
});

test('preserves modifier search and focused Enter when character shortcuts are disabled', () => {
    store.setCharacterShortcutsEnabled(false);
    mount();
    assert(key('k', { ctrlKey: true }).defaultPrevented);
    assert(key('K', { metaKey: true }).defaultPrevented);
    assert.equal(search.mock.calls.length, 2);
    activeElement = commit;
    assert(key('Enter').defaultPrevented);
    assert.deepEqual(visit.mock.calls, [['/@developer/commit']]);
    for (const value of ['Escape', 'Tab', 'ArrowDown', 'ArrowUp'])
        assert.equal(key(value).defaultPrevented, false);
});

test('disables focused commit actions and restores them when re-enabled', () => {
    activeElement = commit;
    mount();
    for (const value of ['r', 'q', 'o', 'y', 'a', 'x'])
        assert(key(value).defaultPrevented);
    assert.equal(commit.action.mock.calls.length, 6);
    store.setCharacterShortcutsEnabled(false);
    for (const value of ['r', 'q', 'o', 'y', 'a', 'x'])
        assert.equal(key(value).defaultPrevented, false);
    assert.equal(commit.action.mock.calls.length, 6);
    store.setCharacterShortcutsEnabled(true);
    key('r');
    assert.equal(commit.action.mock.calls.length, 7);
});

test('cancels a pending character sequence when disabled and still expires sequences', () => {
    vi.useFakeTimers();
    mount();
    key('g');
    store.setCharacterShortcutsEnabled(false);
    assert.equal(key('p').defaultPrevented, false);
    store.setCharacterShortcutsEnabled(true);
    key('p');
    assert.equal(visit.mock.calls.length, 0);
    key('g');
    vi.advanceTimersByTime(1001);
    key('p');
    assert.equal(visit.mock.calls.length, 0);
    key('g');
    key('s');
    assert.deepEqual(visit.mock.calls, [['/settings/profile']]);
});

test('persists across module reloads and responds to other-tab changes and storage clearing', async () => {
    store.setCharacterShortcutsEnabled(false);
    assert.equal(values.get('character-shortcuts'), 'false');
    vi.resetModules();
    store = await import('../../resources/js/hooks/use-character-shortcuts');
    assert.equal(store.characterShortcutsEnabled(), false);
    store.useCharacterShortcuts();
    store.setCharacterShortcutsEnabled(true);
    values.set('character-shortcuts', 'false');
    storage('unrelated');
    assert.equal(store.characterShortcutsEnabled(), true);
    storage('character-shortcuts');
    assert.equal(store.characterShortcutsEnabled(), false);
    values.clear();
    storage(null);
    assert.equal(store.characterShortcutsEnabled(), true);
});

test('remains usable for the session when browser storage is blocked', () => {
    Object.defineProperty(browser, 'localStorage', {
        configurable: true,
        get: () => {
            throw new Error('Blocked');
        },
    });
    assert.equal(store.characterShortcutsEnabled(), true);
    mount();
    store.setCharacterShortcutsEnabled(false);
    assert.equal(key('c').defaultPrevented, false);
    store.setCharacterShortcutsEnabled(true);
    key('c');
    assert.equal(compose.mock.calls.length, 1);
});

test('leaves text entry, dialogs, handled events and composition alone', () => {
    mount();
    for (const tagName of ['INPUT', 'TEXTAREA', 'SELECT', 'DIV']) {
        const target = new Element();
        target.tagName = tagName;
        target.isContentEditable = tagName === 'DIV';
        for (const value of ['c', '/', '?', 'g'])
            assert.equal(key(value, { target }).defaultPrevented, false);
    }
    dialog = true;
    assert.equal(key('c').defaultPrevented, false);
    dialog = false;
    assert.equal(key('c', { isComposing: true }).defaultPrevented, false);
    key('c', { prevented: true });
    assert.equal(
        compose.mock.calls.length +
            help.mock.calls.length +
            search.mock.calls.length +
            visit.mock.calls.length,
        0,
    );
});

test('removes keyboard listeners on unmount', () => {
    mount();
    effects.splice(0).forEach((cleanup) => cleanup());
    key('c');
    assert.equal(compose.mock.calls.length, 0);
});

test('has a safe server default without a browser', () => {
    vi.stubGlobal('window', undefined);
    assert.equal(store.characterShortcutsEnabled(), true);
});
