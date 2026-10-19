import { useEffect } from 'react';

/**
 * Keeps a page that fits the screen from scrolling or bouncing on phones. The open keyboard still pans the page to
 * show the focused field, and not every browser pans it back once the keyboard closes, so this does.
 */
export function useFixedPage(isFixed: boolean): void {
    useEffect(() => {
        if (!isFixed) {
            return;
        }

        const root = document.documentElement;
        const viewport = window.visualViewport;

        const restore = () => {
            if (
                !root.hasAttribute('data-keyboard-open') &&
                window.scrollY !== 0
            ) {
                window.scrollTo(0, 0);
            }
        };

        root.dataset.fixedPage = '';
        viewport?.addEventListener('resize', restore);

        return () => {
            delete root.dataset.fixedPage;
            viewport?.removeEventListener('resize', restore);
        };
    }, [isFixed]);
}
