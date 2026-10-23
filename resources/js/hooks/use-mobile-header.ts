import type { RefObject } from 'react';
import { useEffect } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { restingMobileHeader, scrolledMobileHeader } from '@/lib/mobile-header';

/**
 * Brings the mobile header back down while scrolling up, by marking the root with `data-mobile-header-shown`. The
 * header leads the page content, so where the content starts is where the header sits in the flow.
 */
export function useMobileHeader(header: RefObject<HTMLElement | null>): void {
    const isMobile = useIsMobile();

    useEffect(() => {
        const content = header.current?.parentElement;

        if (!isMobile || !content) {
            return;
        }

        const root = document.documentElement;
        // iOS bounces past either end, and the bounce back would read as scrolling the other way.
        const scrollY = (): number =>
            Math.min(
                Math.max(window.scrollY, 0),
                root.scrollHeight - window.innerHeight,
            );
        let scroll = restingMobileHeader(scrollY());

        const onScroll = () => {
            scroll = scrolledMobileHeader(
                scroll,
                scrollY(),
                content.getBoundingClientRect().top >= 0,
                window.innerHeight,
            );
            root.toggleAttribute('data-mobile-header-shown', scroll.isShown);
        };

        window.addEventListener('scroll', onScroll, { passive: true });

        return () => {
            window.removeEventListener('scroll', onScroll);
            root.removeAttribute('data-mobile-header-shown');
        };
    }, [header, isMobile]);
}
