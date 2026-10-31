import type { Message } from '@/types';

export function isBehindLatest(
    scroller: HTMLElement,
    messages: Message[],
): boolean {
    let remaining = 25;
    let boundary: Message | undefined;
    for (let index = messages.length - 1; index >= 0; index--) {
        if (!messages[index].event && --remaining === 0) {
            boundary = messages[index];
            break;
        }
    }
    const bubble = boundary
        ? scroller.querySelector<HTMLElement>(
              `[data-scroll-anchor="${CSS.escape(boundary.client_id)}"]`,
          )
        : null;

    return (
        bubble !== null &&
        bubble.getBoundingClientRect().top >=
            scroller.getBoundingClientRect().bottom
    );
}
