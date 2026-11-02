const slideDuration = 300;
const growDuration = 200;
const fadeOutDuration = 120;
const fadeInDuration = 180;
const easing = 'cubic-bezier(0.33, 1, 0.68, 1)';

let pendingTimeout = 0;
let slidingTimeout = 0;
let heldMedia: HTMLElement[] = [];
let heldText: HTMLElement[] = [];

function release(): void {
    [...heldMedia, ...heldText].forEach((element) => {
        element.getAnimations().forEach((animation) => animation.cancel());
        element.style.width = '';
        element.style.height = '';
        element.style.overflowY = '';
    });
    heldMedia = [];
    heldText = [];
}

let diffTimeouts: number[] = [];
let diffed: HTMLElement[] = [];
let hunks: HTMLElement[] = [];

function clearDiff(): void {
    diffTimeouts.forEach((timeout) => window.clearTimeout(timeout));
    diffed.forEach((element) => delete element.dataset.diff);
    hunks.forEach((element) => {
        delete element.dataset.hunk;
        delete element.dataset.hunkShown;
    });
    diffTimeouts = [];
    diffed = [];
    hunks = [];
}

const isOnScreen = (element: Element): boolean => {
    const { top, bottom } = element.getBoundingClientRect();

    return bottom > 0 && top < window.innerHeight;
};

const isInCommit = (element: Element): boolean =>
    element.closest('[data-commit]') !== null;

function markRemoved(text: HTMLElement[]): void {
    diffed = text;
    text.forEach((element) => (element.dataset.diff = 'del'));
}

/**
 * Commit text that re-wrapped on screen lands like the added side of a diff, and its hash shows the hunk header for
 * how many lines it went from and to. Both fade away again.
 */
function markAdded(
    text: { element: HTMLElement; from: number; to: number }[],
): void {
    diffed = text.map(({ element }) => element);
    hunks = [];

    text.forEach(({ element, from, to }) => {
        element.dataset.diff = 'add';

        const commit = element.closest('[data-commit]');
        const hash = [
            ...(commit?.querySelectorAll<HTMLElement>('[data-commit-hash]') ??
                []),
        ].find((candidate) => candidate.closest('[data-commit]') === commit);
        const lineHeight = Number.parseFloat(
            getComputedStyle(element).lineHeight,
        );
        const lines = (height: number): number =>
            Math.max(1, Math.round(height / lineHeight));

        if (hash !== undefined && lines(from) !== lines(to)) {
            hash.dataset.hunk = `@@ -${lines(from)} +${lines(to)} @@`;
            hunks.push(hash);
        }
    });

    requestAnimationFrame(() =>
        hunks.forEach((hash) => (hash.dataset.hunkShown = '')),
    );
    diffTimeouts.push(
        window.setTimeout(
            () =>
                diffed.forEach((element) => (element.dataset.diff = 'settle')),
            250,
        ),
        window.setTimeout(
            () => hunks.forEach((hash) => delete hash.dataset.hunkShown),
            1000,
        ),
        window.setTimeout(clearDiff, 1300),
    );
}

const heightOf = (element: HTMLElement): number =>
    element.getBoundingClientRect().height;

function animateHeight(
    element: HTMLElement,
    from: number,
    to: number,
): Promise<void> {
    element.style.height = `${to}px`;

    return element
        .animate([{ height: `${from}px` }, { height: `${to}px` }], {
            duration: growDuration,
            easing,
        })
        .finished.then(
            () => undefined,
            () => undefined,
        );
}

/**
 * Lays the page out in its final state for a moment, without painting or transitions, to read where things end up.
 */
function inFinalLayout<T>(open: boolean, read: () => T): T {
    const layout = document.querySelector<HTMLElement>('[data-nav]');

    if (layout === null) {
        return read();
    }

    const current = layout.dataset.nav;
    const root = document.documentElement;

    root.dataset.navMeasuring = '';
    layout.dataset.nav = open ? 'expanded' : 'collapsed';
    const result = read();
    layout.dataset.nav = current;
    void layout.offsetWidth;
    delete root.dataset.navMeasuring;

    return result;
}

/**
 * Where a text's lines break, so text that wraps the same either way can be left alone. Only text counts: a code
 * block's box follows the feed's width on its own, while its code counts once it wraps.
 */
function lineBreaks(element: HTMLElement): string {
    const { top, left } = element.getBoundingClientRect();
    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    const range = document.createRange();
    const lines: string[] = [];

    for (
        let node = walker.nextNode();
        node !== null;
        node = walker.nextNode()
    ) {
        range.selectNodeContents(node);

        for (const rect of range.getClientRects()) {
            lines.push(
                `${Math.round(rect.top - top)}:${Math.round(rect.left - left)}:${Math.round(rect.width)}`,
            );
        }
    }

    return lines.join(' ');
}

let pin: { anchor: HTMLElement; top: number; until: number } | null = null;

function rowNearestCenter(content: Element): HTMLElement | null {
    const center = window.innerHeight / 2;
    let nearest: HTMLElement | null = null;
    let nearestDistance = Infinity;

    const selector = '[data-commit], [data-notification-group]';

    content.querySelectorAll<HTMLElement>(selector).forEach((row) => {
        if (row.parentElement?.closest(selector)) {
            return;
        }

        const { top, bottom } = row.getBoundingClientRect();

        if (bottom < 0 || top > window.innerHeight) {
            return;
        }

        const distance =
            top <= center && bottom >= center
                ? 0
                : Math.min(Math.abs(top - center), Math.abs(bottom - center));

        if (distance < nearestDistance) {
            nearest = row;
            nearestDistance = distance;
        }
    });

    return nearest;
}

/**
 * Keeps the row nearest the middle of the screen where it is while everything around it changes size. Quick
 * clicks extend the hold and keep the same row, so a burst of them can't walk the page away.
 */
function pinScroll(content: Element, duration: number): void {
    const until = performance.now() + duration;

    if (pin !== null) {
        pin.until = until;

        return;
    }

    // At the top, the page stays at the top: what's above can only shrink into it, so holding a row would walk
    // the page down a little with every expand.
    const anchor = window.scrollY < 1 ? null : rowNearestCenter(content);

    if (anchor === null) {
        return;
    }

    const root = document.documentElement;
    const current = { anchor, top: anchor.getBoundingClientRect().top, until };
    let frame = 0;
    let heldAt = window.scrollY;
    // Scrolling yourself, by wheel, touch, keys or the scrollbar, leaves the page somewhere the hold didn't put it.
    const onScroll = (): void => {
        if (Math.abs(window.scrollY - heldAt) > 1) {
            unpin();
        }
    };
    const unpin = (): void => {
        cancelAnimationFrame(frame);
        removeEventListener('scroll', onScroll);

        if (pin === current) {
            pin = null;
            delete root.dataset.navPinning;
        }
    };

    pin = current;
    root.dataset.navPinning = '';
    addEventListener('scroll', onScroll, { passive: true });

    const hold = (now: number): void => {
        if (pin !== current || !anchor.isConnected) {
            unpin();

            return;
        }

        window.scrollBy(0, anchor.getBoundingClientRect().top - current.top);
        heldAt = window.scrollY;

        if (now < current.until) {
            frame = requestAnimationFrame(hold);

            return;
        }

        unpin();
    };

    frame = requestAnimationFrame(hold);
}

const isNearViewport = (element: Element): boolean => {
    const { top, bottom } = element.getBoundingClientRect();

    return bottom > -window.innerHeight && top < window.innerHeight * 2;
};

/**
 * What's within a screen of the viewport. Commits further away are left to re-wrap on their own: content-visibility
 * skips their layout, and the scroll hold keeps the view still. Commits are checked before what's in them, so the
 * skipped ones aren't laid out just to be measured.
 */
function nearby(content: Element, selector: string): HTMLElement[] {
    const commits = [...content.querySelectorAll('[data-commit]')].filter(
        (commit) =>
            commit.parentElement?.closest('[data-commit]') === null &&
            isNearViewport(commit),
    );
    const outside = [...content.querySelectorAll<HTMLElement>(selector)].filter(
        (element) =>
            element.closest('[data-commit]') === null &&
            isNearViewport(element),
    );

    return [
        ...commits.flatMap((commit) => [
            ...commit.querySelectorAll<HTMLElement>(selector),
        ]),
        ...outside,
    ];
}

/**
 * Text and images change width with the slide but not height: text keeps its width, so it doesn't re-wrap, and images
 * keep their height. Once the sidebar has collapsed, the text re-wraps and everything grows into its new height.
 * Expanding plays it backwards: everything settles into its new height first, then narrows with the slide.
 */
function slideThenGrow(open: boolean, slide: () => void): void {
    const content = document.querySelector('[data-nav-content]');

    if (content === null) {
        slide();

        return;
    }

    const media = nearby(
        content,
        ':is(img[data-single-image]:not([data-fallback]), [data-image-grid])',
    );
    const text = nearby(content, '[data-commit-body]');
    pinScroll(content, growDuration + slideDuration + 120);

    const onScreen = text.filter(isOnScreen);
    const breaks = onScreen.map(lineBreaks);
    const [final, finalBreaks] = inFinalLayout(open, () => [
        [...media, ...text].map((element) => element.getBoundingClientRect()),
        onScreen.map(lineBreaks),
    ]);
    const rewrapping = onScreen.filter(
        (_, index) => breaks[index] !== finalBreaks[index],
    );
    const finalMedia = final.slice(0, media.length);
    const finalText = final.slice(media.length);

    heldMedia = media;
    heldText = text;

    const grow = (fadedOut: Animation[] = []): Promise<void> => {
        const mediaFrom = media.map(heightOf);
        const textFrom = text.map(heightOf);

        text.forEach((element, index) => {
            element.style.width = `${finalText[index].width}px`;
        });

        const textTo = text.map(heightOf);

        // Only collapsing faded the text out, so only it fades back in.
        fadedOut.forEach((animation) => {
            (animation.effect as KeyframeEffect).target?.animate(
                [{ opacity: 0 }, { opacity: 1 }],
                { duration: fadeInDuration, easing: 'ease-out' },
            );
            animation.cancel();
        });
        markAdded(
            rewrapping.filter(isInCommit).map((element) => ({
                element,
                from: textFrom[text.indexOf(element)],
                to: textTo[text.indexOf(element)],
            })),
        );

        text.forEach((element) => {
            element.style.overflowY = 'clip';
        });

        const growing = Promise.all([
            ...media.map((element, index) =>
                animateHeight(
                    element,
                    mediaFrom[index],
                    finalMedia[index].height,
                ),
            ),
            ...text.map((element, index) =>
                animateHeight(element, textFrom[index], textTo[index]),
            ),
        ]).then(() => undefined);

        return growing;
    };

    if (open) {
        void grow();
        pendingTimeout = window.setTimeout(() => {
            slide();
            pendingTimeout = window.setTimeout(release, slideDuration + 20);
        }, growDuration);

        return;
    }

    const mediaHeights = media.map(heightOf);
    const textWidths = text.map(
        (element) => element.getBoundingClientRect().width,
    );

    media.forEach((element, index) => {
        element.style.height = `${mediaHeights[index]}px`;
    });
    text.forEach((element, index) => {
        element.style.width = `${textWidths[index]}px`;
    });
    slide();

    // The text re-wraps in one go once the slide ends, so what's on screen is marked removed, like a diff, and fades
    // out just before, then comes back marked added.
    diffTimeouts.push(
        window.setTimeout(
            () => markRemoved(rewrapping.filter(isInCommit)),
            slideDuration - fadeOutDuration - 70,
        ),
    );
    const fadedOut = rewrapping.map((element) =>
        element.animate([{ opacity: 1 }, { opacity: 0 }], {
            duration: fadeOutDuration,
            delay: slideDuration - fadeOutDuration,
            easing: 'ease-in',
            fill: 'forwards',
        }),
    );

    pendingTimeout = window.setTimeout(() => {
        void grow(fadedOut).then(() => {
            if (heldMedia === media) {
                release();
            }
        });
    }, slideDuration + 20);
}

/**
 * Applies `apply`, which switches the layout, inside the choreography. Each call cancels the one before it, so quick
 * clicks leave nothing half-done.
 */
export function runNavCollapse(open: boolean, apply: () => void): void {
    const root = document.documentElement;

    window.clearTimeout(pendingTimeout);
    release();
    clearDiff();

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        apply();

        return;
    }

    const slide = (): void => {
        window.clearTimeout(slidingTimeout);
        root.dataset.navSliding = '';
        apply();
        slidingTimeout = window.setTimeout(() => {
            delete root.dataset.navSliding;
        }, slideDuration + 20);
    };

    // Still sliding: turn back from where it is, rather than restarting from an end.
    if ('navSliding' in root.dataset) {
        const content = document.querySelector('[data-nav-content]');

        if (content !== null) {
            pinScroll(content, slideDuration + 120);
        }

        slide();

        return;
    }

    slideThenGrow(open, slide);
}
