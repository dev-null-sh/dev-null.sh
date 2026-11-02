import type { Message } from '@/types';

export function isBehindLatest(
    scroller: HTMLElement,
    messages: Message[],
): boolean {
    const boundary = messages.filter((message) => !message.event).at(-25);
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
