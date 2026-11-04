/**
 * Finds the active reply or commit's keyboard action. Only reply falls back to the
 * main commit on a detail page when keyboard focus is elsewhere.
 */
export function commitActionForShortcut(
    key: string,
    activeTarget: Element | null,
    pageCommit: Element | null,
): HTMLButtonElement | HTMLTextAreaElement | null {
    const target = activeTarget ?? (key === 'r' ? pageCommit : null);
    const action = target?.querySelector<HTMLButtonElement>(
        `button[aria-keyshortcuts="${CSS.escape(key)}"]`,
    );

    if (action && !action.closest('[inert]')) {
        return action;
    }

    if (key !== 'r' || !target) {
        return null;
    }

    // A reply button loses its shortcut while its composer is open.
    // Only focus the composer belonging to this commit or reply, not a nested one.
    return (
        Array.from(
            target.querySelectorAll<HTMLTextAreaElement>(
                '[data-test="reply-composer"] textarea',
            ),
        ).find(
            (textarea) =>
                textarea.closest('[data-reply], [data-commit]') === target &&
                !textarea.closest('[inert]'),
        ) ?? null
    );
}
