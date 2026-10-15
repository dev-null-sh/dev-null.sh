import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import LogController from '@/actions/App/Http/Controllers/LogController';
import { show } from '@/routes/user';
import { edit } from '@/routes/user-profile';
import type { User } from '@/types';

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

    commits[next].focus({ preventScroll: true });
    commits[next].scrollIntoView({ block: 'nearest' });
}

function focusComposer(): void {
    const body = document.getElementById('body');

    if (body) {
        body.focus();

        return;
    }

    router.visit(LogController.show('following'), {
        onFinish: () =>
            requestAnimationFrame(() =>
                document.getElementById('body')?.focus(),
            ),
    });
}

export function useKeyboardShortcuts(user: User, onShowHelp: () => void): void {
    useEffect(() => {
        let awaitingGoTo = false;
        let goToTimeout: ReturnType<typeof setTimeout> | undefined;

        const goTo: Record<string, () => void> = {
            l: () => router.visit(LogController.show('following')),
            e: () => router.visit(LogController.show('everyone')),
            p: () => router.visit(show(user)),
            s: () => router.visit(edit()),
        };

        const onKeyDown = (event: KeyboardEvent) => {
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

            const actions: Record<string, () => void> = {
                j: () => moveCommitFocus(1),
                k: () => moveCommitFocus(-1),
                c: focusComposer,
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
    }, [user, onShowHelp]);
}
