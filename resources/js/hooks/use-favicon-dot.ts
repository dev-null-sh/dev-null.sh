import { useEffect } from 'react';

export function useFaviconDot(hasDot: boolean): void {
    useEffect(() => {
        if (!hasDot) {
            return;
        }

        const icons =
            document.querySelectorAll<HTMLLinkElement>('link[rel="icon"]');

        icons.forEach((icon) => {
            icon.href = icon.href.replace('/favicon.', '/favicon-unread.');
        });

        return () =>
            icons.forEach((icon) => {
                icon.href = icon.href.replace('/favicon-unread.', '/favicon.');
            });
    }, [hasDot]);
}
