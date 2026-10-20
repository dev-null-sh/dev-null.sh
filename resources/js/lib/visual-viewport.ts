const keyboardMinHeight = 120;

/**
 * Keeps the part of the layout viewport the user can see in CSS: `--visual-viewport-top`, `--visual-viewport-height`,
 * and `--visual-viewport-bottom`, how far its bottom edge sits above the layout viewport's. On iOS the keyboard covers
 * the page instead of resizing it, so anything fixed that must stay visible above the keyboard sizes itself with
 * these, falling back to the full viewport where they are unset. While the keyboard takes room, the root also carries
 * `data-keyboard-open`.
 */
export function trackVisualViewport(): void {
    const viewport =
        typeof window === 'undefined' ? null : window.visualViewport;

    if (viewport === null) {
        return;
    }

    const root = document.documentElement;

    const update = () => {
        const bottom = Math.max(
            0,
            root.clientHeight - viewport.offsetTop - viewport.height,
        );

        root.style.setProperty(
            '--visual-viewport-top',
            `${viewport.offsetTop}px`,
        );
        root.style.setProperty(
            '--visual-viewport-height',
            `${viewport.height}px`,
        );
        root.style.setProperty('--visual-viewport-bottom', `${bottom}px`);
        root.toggleAttribute(
            'data-keyboard-open',
            isKeyboardOpen(
                root.hasAttribute('data-keyboard-open'),
                isTyping(),
                root.clientHeight - viewport.height * viewport.scale,
            ),
        );
    };

    update();
    viewport.addEventListener('resize', update);
    viewport.addEventListener('scroll', update);
    document.addEventListener('focusin', update);
}

/**
 * Only a focused field opens the keyboard, but the field loses focus before the keyboard is gone, so once open it stays
 * open until the room it took comes back. A tap that blurs the field then lands before the layout moves.
 */
export function isKeyboardOpen(
    wasOpen: boolean,
    isTyping: boolean,
    coveredHeight: number,
): boolean {
    return (wasOpen || isTyping) && coveredHeight > keyboardMinHeight;
}

function isTyping(): boolean {
    const element = document.activeElement;

    return (
        element instanceof HTMLTextAreaElement ||
        (element instanceof HTMLInputElement &&
            !['button', 'checkbox', 'radio', 'range', 'submit'].includes(
                element.type,
            )) ||
        (element instanceof HTMLElement && element.isContentEditable)
    );
}
