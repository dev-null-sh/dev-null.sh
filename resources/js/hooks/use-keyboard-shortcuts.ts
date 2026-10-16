import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { signIn } from '@/lib/auth';
import { show } from '@/routes/user';
import { edit } from '@/routes/user-profile';
import type { Log, User } from '@/types';

function isTyping(target: EventTarget | null): boolean {
    return (
        target instanceof HTMLElement &&
        (target.isContentEditable ||
            ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
    );
}

function moveCommitFocus(step: 1 | -1): void {
    const commits = [
        ...document.querySelectorAll<HTMLElement>('[data-commit]'),
    ];

    if (commits.length === 0) {
        return;
    }

    const current = commits.findIndex((commit) =>
        commit.contains(document.activeElement),
    );
    const next =
        current === -1
            ? step === 1
                ? 0
                : commits.length - 1
            : Math.min(Math.max(current + step, 0), commits.length - 1);

    focusCommit(commits[next]);
}

export function focusCommit(commit: HTMLElement): void {
    commit.focus({ preventScroll: true });
    commit.scrollIntoView({ block: 'nearest' });
}

function focusedCommitAction(key: string): HTMLButtonElement | null {
    return (
        document.activeElement
            ?.closest('[data-commit]')
            ?.querySelector<HTMLButtonElement>(
                `button[aria-keyshortcuts="${CSS.escape(key)}"]`,
            ) ?? null
    );
}

function openFocusedCommit(): boolean {
    const commit = document.activeElement;

    if (
        !(commit instanceof HTMLElement) ||
        !commit.matches('[data-commit]') ||
        !commit.dataset.permalink
    ) {
        return false;
    }

    router.visit(commit.dataset.permalink);

    return true;
}

function focusComposer(log: Log): void {
    const body = document.getElementById('body');

    if (body) {
        body.focus();

        return;
    }

    router.visit(LogController.show(log), {
        onFinish: () =>
            requestAnimationFrame(() =>
                document.getElementById('body')?.focus(),
            ),
    });
}

/**
 * Guests have no log, profile, or settings to go to, nor a composer, so those shortcuts take them to sign in.
 */
export function useKeyboardShortcuts(
    user: User | null,
    log: Log | null,
    onShowHelp: () => void,
    onSearch: () => void,
): void {
    useEffect(() => {
        let awaitingGoTo = false;
        let goToTimeout: ReturnType<typeof setTimeout> | undefined;

        const goTo: Record<string, () => void> =
            user === null || log === null
                ? { l: signIn, e: signIn, p: signIn, s: signIn }
                : {
                      l: () => router.visit(LogController.show(log)),
                      e: () => router.visit(LogController.show('everyone')),
                      p: () => router.visit(show(user)),
                      s: () => router.visit(edit()),
                  };

        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                onSearch();

                return;
            }

            if (
                event.defaultPrevented ||
                event.metaKey ||
                event.ctrlKey ||
                event.altKey ||
                isTyping(event.target) ||
                document.querySelector('[role="dialog"][data-state="open"]')
            ) {
                return;
            }

            if (awaitingGoTo) {
                awaitingGoTo = false;
                clearTimeout(goToTimeout);

                if (goTo[event.key]) {
                    event.preventDefault();
                    goTo[event.key]();
                }

                return;
            }

            if (event.key === 'Enter') {
                if (openFocusedCommit()) {
                    event.preventDefault();
                }

                return;
            }

            const commitAction = focusedCommitAction(event.key);

            if (commitAction) {
                event.preventDefault();
                commitAction.click();

                return;
            }

            const actions: Record<string, () => void> = {
                j: () => moveCommitFocus(1),
                k: () => moveCommitFocus(-1),
                c: () => (log === null ? signIn() : focusComposer(log)),
                '/': onSearch,
                '?': onShowHelp,
                g: () => {
                    awaitingGoTo = true;
                    goToTimeout = setTimeout(() => {
                        awaitingGoTo = false;
                    }, 1000);
                },
            };

            if (actions[event.key]) {
                event.preventDefault();
                actions[event.key]();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
            clearTimeout(goToTimeout);
        };
    }, [user, log, onShowHelp, onSearch]);
}
