import type { MouseEvent } from 'react';

export function scrollLogToTop(event?: MouseEvent): void {
    if (
        event &&
        (event.defaultPrevented ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey)
    ) {
        return;
    }

    event?.preventDefault();
    window.dispatchEvent(new Event('log:scroll-top'));
}

export function animateScrollToTop(): VoidFunction {
    const top = window.scrollY;
    if (
        top <= 0 ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        window.scrollTo({ top: 0, behavior: 'instant' });
        return () => {};
    }

    const started = performance.now();
    const duration = Math.min(800, 250 + Math.sqrt(top) * 5);
    let frame: number;
    const cancel = () => {
        cancelAnimationFrame(frame);
        window.removeEventListener('wheel', cancel);
        window.removeEventListener('touchstart', cancel);
        window.removeEventListener('pointerdown', cancel);
        window.removeEventListener('keydown', onKeyDown);
    };
    const onKeyDown = (event: KeyboardEvent) => {
        if (
            [
                'ArrowUp',
                'ArrowDown',
                'PageUp',
                'PageDown',
                'Home',
                'End',
                ' ',
            ].includes(event.key)
        ) {
            cancel();
        }
    };
    const step = (now: number) => {
        const progress = Math.min((now - started) / duration, 1);
        window.scrollTo({
            top: top * (1 - progress) ** 3,
            behavior: 'instant',
        });
        if (progress < 1) {
            frame = requestAnimationFrame(step);
        } else {
            cancel();
        }
    };
    window.addEventListener('wheel', cancel, { passive: true });
    window.addEventListener('touchstart', cancel, { passive: true });
    window.addEventListener('pointerdown', cancel);
    window.addEventListener('keydown', onKeyDown);
    frame = requestAnimationFrame(step);

    return cancel;
}
