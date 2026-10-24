import {
    drawnRect,
    frameAt,
    intersect,
    peekRadius,
    peekRect,
    restingFrame,
} from '@/lib/image-peek-geometry';
import type { ObjectFit, PeekFrame, Rect } from '@/lib/image-peek-geometry';

/**
 * The images that can be peeked at, marked where they are rendered.
 */
export const peekableSelector = 'img[data-image-peek]';

/**
 * A press shorter than this is a click, and nothing about it changes.
 */
export const holdDelay = 200;

/**
 * How long the image takes to grow out and settle, and how long it takes to go back. Quicker going back: letting go
 * should feel immediate.
 */
const growDuration = 340;
const returnDuration = 240;

/**
 * How much faster a grow cut short retraces its path back.
 */
const reverseSpeed = 1.6;

/**
 * A pointer that wanders further than this before the peek starts is scrolling or selecting, not holding.
 */
const slop = 8;

/**
 * The overshoot at the end of the grow, the "pop".
 */
const popScale = 1.025;

/**
 * How long after letting go the click a held press ends in is still recognised, and swallowed, however long it was
 * held.
 */
const clickAfterRelease = 1000;

const layer = '100';

function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function toRect(rect: DOMRect): Rect {
    return {
        left: rect.left,
        top: rect.top,
        width: rect.width,
        height: rect.height,
    };
}

/**
 * The rounded corners the picture shows on the page. The page rounds a grid of images on the grid, not on each image,
 * so the nearest clipping ancestor with rounded corners counts too.
 */
function roundedCorners(image: HTMLImageElement): number {
    let element: Element | null = image;

    for (let depth = 0; element !== null && depth < 4; depth++) {
        const style = getComputedStyle(element);
        const value = style.borderTopLeftRadius;
        const radius = value.endsWith('%')
            ? (Number.parseFloat(value) / 100) *
              element.getBoundingClientRect().width
            : Number.parseFloat(value);

        if (radius > 0 && (element === image || style.overflow !== 'visible')) {
            return radius;
        }

        element = element.parentElement;
    }

    return 0;
}

/**
 * Where the picture is on the page now, as the peek must look to sit exactly on top of it.
 */
function sourceFrame(image: HTMLImageElement, peek: Rect): PeekFrame | null {
    if (!image.isConnected) {
        return null;
    }

    const box = toRect(image.getBoundingClientRect());
    const style = getComputedStyle(image);
    const drawn = drawnRect(
        box,
        { width: image.naturalWidth, height: image.naturalHeight },
        style.objectFit as ObjectFit,
        style.objectPosition,
    );
    const visible = intersect(drawn, box);

    return visible === null
        ? null
        : frameAt(peek, drawn, visible, roundedCorners(image));
}

/**
 * The pop: a touch larger than at rest, around the centre.
 */
function popFrame(peek: Rect): PeekFrame {
    const dx = ((popScale - 1) * peek.width) / 2;
    const dy = ((popScale - 1) * peek.height) / 2;

    return {
        transform: `translate(${String(-dx)}px, ${String(-dy)}px) scale(${String(popScale)}, ${String(popScale)})`,
        clipPath: restingFrame(peekRadius / popScale).clipPath,
    };
}

/**
 * Whether a picture is there to peek at: loaded, and not the stand-in shown for one that failed.
 */
function isReadyToPeek(image: HTMLImageElement): boolean {
    return (
        image.complete &&
        image.naturalWidth > 0 &&
        image.naturalHeight > 0 &&
        image.dataset.fallback === undefined
    );
}

/**
 * One peek at one image: a copy of it lifts off the page, grows to fill the screen and pops, then, when let go, goes
 * back exactly where it came from. The page's own image is only hidden by an animation while the copy is out, so
 * nothing the page renders is ever changed.
 */
class Peek {
    readonly #image: HTMLImageElement;
    readonly #host: HTMLDivElement;
    readonly #backdrop: HTMLDivElement;
    readonly #copy: HTMLDivElement;
    readonly #rect: Rect;
    readonly #onGone: () => void;
    #hiding: Animation | null = null;
    #moving: Animation[] = [];
    #state: 'growing' | 'shown' | 'returning' | 'gone' = 'growing';
    #backstop = 0;

    constructor(image: HTMLImageElement, onGone: () => void) {
        this.#image = image;
        this.#onGone = onGone;
        this.#rect = peekRect(
            { width: window.innerWidth, height: window.innerHeight },
            { width: image.naturalWidth, height: image.naturalHeight },
            image.getBoundingClientRect(),
        );

        const picture = document.createElement('img');

        picture.alt = '';
        picture.draggable = false;
        picture.decoding = 'sync';
        picture.src = image.currentSrc || image.src;
        Object.assign(picture.style, {
            display: 'block',
            width: '100%',
            height: '100%',
            maxWidth: 'none',
            userSelect: 'none',
        });

        this.#backdrop = document.createElement('div');
        Object.assign(this.#backdrop.style, {
            position: 'absolute',
            inset: '0',
            background: 'rgb(0 0 0 / 0.6)',
            opacity: '0',
        });

        this.#copy = document.createElement('div');
        Object.assign(this.#copy.style, {
            position: 'absolute',
            transformOrigin: '0 0',
            left: `${String(this.#rect.left)}px`,
            top: `${String(this.#rect.top)}px`,
            width: `${String(this.#rect.width)}px`,
            height: `${String(this.#rect.height)}px`,
        });
        this.#copy.append(picture);

        this.#host = document.createElement('div');
        this.#host.setAttribute('aria-hidden', 'true');
        this.#host.dataset.test = 'image-peek';
        Object.assign(this.#host.style, {
            position: 'fixed',
            inset: '0',
            zIndex: layer,
            pointerEvents: 'none',
        });
        this.#host.append(this.#backdrop, this.#copy);
    }

    grow(): void {
        const from = sourceFrame(this.#image, this.#rect);

        if (from === null) {
            this.#finish();

            return;
        }

        document.body.append(this.#host);
        // The copy now covers the picture exactly; hide the picture underneath in the same frame, so it seems to lift
        // off.
        this.#hiding = this.#image.animate([{ opacity: 0 }], {
            duration: 0,
            fill: 'forwards',
        });

        const rest = restingFrame(peekRadius);

        if (prefersReducedMotion()) {
            this.#moving = [
                this.#copy.animate(
                    [
                        { ...rest, opacity: 0 },
                        { ...rest, opacity: 1 },
                    ],
                    { duration: 150, fill: 'forwards' },
                ),
                this.#backdrop.animate([{ opacity: 0 }, { opacity: 1 }], {
                    duration: 150,
                    fill: 'forwards',
                }),
            ];
        } else {
            this.#moving = [
                this.#copy.animate(
                    [
                        { ...from, easing: 'cubic-bezier(0.3, 0, 0.2, 1)' },
                        {
                            ...popFrame(this.#rect),
                            offset: 0.78,
                            easing: 'cubic-bezier(0.3, 0, 0.4, 1)',
                        },
                        rest,
                    ],
                    { duration: growDuration, fill: 'forwards' },
                ),
                this.#backdrop.animate([{ opacity: 0 }, { opacity: 1 }], {
                    duration: growDuration * 0.8,
                    fill: 'forwards',
                }),
            ];
        }

        this.#whenMoved(growDuration, () => {
            if (this.#state === 'growing') {
                this.#state = 'shown';
            }
        });
    }

    /**
     * Let go: back to exactly where the picture is now, then gone.
     */
    release(): void {
        if (this.#state === 'returning' || this.#state === 'gone') {
            return;
        }

        const wasGrowing = this.#state === 'growing';

        this.#state = 'returning';

        if (wasGrowing && !prefersReducedMotion()) {
            // Still on its way out: retrace the same path back, a little faster.
            const elapsed = Number(
                this.#moving[0]?.currentTime ?? growDuration,
            );

            for (const animation of this.#moving) {
                animation.updatePlaybackRate(-reverseSpeed);
            }

            this.#whenMoved(elapsed / reverseSpeed, () => this.#finish());

            return;
        }

        const to = sourceFrame(this.#image, this.#rect);
        const old = this.#moving;

        if (to === null || prefersReducedMotion()) {
            // The picture is gone from the page, or motion is unwelcome: fade out where it is.
            this.#moving = [
                this.#copy.animate(
                    [
                        { ...restingFrame(peekRadius), opacity: 1 },
                        { ...restingFrame(peekRadius), opacity: 0 },
                    ],
                    { duration: 150, fill: 'forwards' },
                ),
                this.#backdrop.animate([{ opacity: 1 }, { opacity: 0 }], {
                    duration: 150,
                    fill: 'forwards',
                }),
            ];
        } else {
            this.#moving = [
                this.#copy.animate([restingFrame(peekRadius), to], {
                    duration: returnDuration,
                    easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
                    fill: 'forwards',
                }),
                this.#backdrop.animate([{ opacity: 1 }, { opacity: 0 }], {
                    duration: returnDuration,
                    easing: 'ease-out',
                    fill: 'forwards',
                }),
            ];
        }

        // Each new animation starts from where the last one is, so the old ones are cancelled only once replaced.
        queueMicrotask(() => {
            for (const animation of old) {
                animation.cancel();
            }
        });
        this.#whenMoved(returnDuration, () => this.#finish());
    }

    /**
     * Gone at once, with the page exactly as it was.
     */
    destroy(): void {
        this.#finish();
    }

    /**
     * Runs `then` when the current animations finish, or after `ms` in case they never report back.
     */
    #whenMoved(ms: number, then: () => void): void {
        const moving = this.#moving;
        let isDone = false;
        const once = () => {
            if (!isDone && this.#moving === moving) {
                isDone = true;
                clearTimeout(this.#backstop);
                then();
            }
        };

        clearTimeout(this.#backstop);
        void Promise.all(moving.map((animation) => animation.finished)).then(
            once,
            () => undefined,
        );
        this.#backstop = window.setTimeout(once, ms + 120);
    }

    #finish(): void {
        if (this.#state === 'gone') {
            return;
        }

        this.#state = 'gone';
        clearTimeout(this.#backstop);

        // Show the picture again and take the copy away in the same frame, so there is never a gap or a double.
        for (const animation of [
            ...this.#moving,
            ...(this.#hiding === null ? [] : [this.#hiding]),
        ]) {
            // Cancelling rejects `finished`; that is expected here.
            animation.finished.catch(() => undefined);
            animation.cancel();
        }

        this.#host.remove();
        this.#onGone();
    }
}

type Press = {
    image: HTMLImageElement;
    x: number;
    y: number;
    pointerId: number;
    timer: number;
};

/**
 * Press and hold an image to peek at it: it grows out of the page and pops, and goes back exactly where it was when
 * let go, with a mouse or a finger. A quick click or tap does what it always did, such as opening the photo viewer;
 * only the click a peek ends in is swallowed.
 *
 * Moving, scrolling or dragging before the peek starts cancels quietly. While it is out, the page does not scroll
 * under it, and Escape, switching away or losing the pointer all send it back.
 */
export class ImagePeek {
    #press: Press | null = null;
    #peek: Peek | null = null;

    /**
     * The click to swallow: on `target`, until `until`, or, while the press is still held, with no end yet.
     */
    #swallow: { target: Element; until: number | null } | null = null;

    constructor(signal: AbortSignal) {
        const capture = { capture: true, signal };

        window.addEventListener('pointerdown', this.#onPointerDown, capture);
        window.addEventListener('pointermove', this.#onPointerMove, {
            ...capture,
            passive: true,
        });
        window.addEventListener('pointerup', this.#onPointerUp, capture);
        window.addEventListener('pointercancel', this.#onPointerUp, capture);
        window.addEventListener('scroll', this.#cancelPress, {
            ...capture,
            passive: true,
        });
        // While the image is out, the page stays still under it, so it can go back to the same place.
        window.addEventListener('wheel', this.#holdStill, {
            ...capture,
            passive: false,
        });
        window.addEventListener('touchmove', this.#holdStill, {
            ...capture,
            passive: false,
        });
        window.addEventListener('keydown', this.#onKeyDown, capture);
        window.addEventListener('dragstart', this.#onDragStart, capture);
        window.addEventListener('contextmenu', this.#onContextMenu, capture);
        window.addEventListener('click', this.#onClick, capture);
        window.addEventListener('blur', this.#onWindowBlur, { signal });
        document.addEventListener('visibilitychange', this.#release, {
            signal,
        });
        signal.addEventListener('abort', () => this.#destroy(), { once: true });
    }

    #destroy(): void {
        this.#cancelPress();
        this.#peek?.destroy();
        this.#peek = null;
    }

    readonly #onPointerDown = (event: PointerEvent): void => {
        this.#cancelPress();

        // A new press means the last one ended, even if its letting go was never heard, as outside the window.
        if (this.#swallow?.until === null) {
            this.#swallow = null;
        }

        if (
            this.#peek !== null ||
            !event.isPrimary ||
            event.button !== 0 ||
            event.altKey ||
            event.ctrlKey ||
            event.metaKey ||
            event.shiftKey ||
            !(event.target instanceof Element)
        ) {
            return;
        }

        const image = event.target.closest<HTMLImageElement>(peekableSelector);

        if (image === null || !isReadyToPeek(image)) {
            return;
        }

        // Decode the picture now, during the wait, so the peek's first frame is never blank.
        void image.decode().catch(() => undefined);
        this.#press = {
            image,
            x: event.clientX,
            y: event.clientY,
            pointerId: event.pointerId,
            timer: window.setTimeout(this.#startPeek, holdDelay),
        };
    };

    readonly #onPointerMove = (event: PointerEvent): void => {
        const press = this.#press;

        if (
            press !== null &&
            event.pointerId === press.pointerId &&
            Math.hypot(event.clientX - press.x, event.clientY - press.y) > slop
        ) {
            this.#cancelPress();
        }
    };

    readonly #startPeek = (): void => {
        const press = this.#press;

        if (press === null) {
            return;
        }

        this.#press = null;

        if (!isReadyToPeek(press.image)) {
            return;
        }

        // Held past a click: the click the release ends in lands on the picture, or its link, and must not also open
        // it.
        this.#swallow = {
            target: press.image.closest('a, button') ?? press.image,
            until: null,
        };

        const peek = new Peek(press.image, () => {
            if (this.#peek === peek) {
                this.#peek = null;
            }
        });

        this.#peek = peek;
        peek.grow();
    };

    /**
     * Letting go: the click it ends in comes next, and the time to recognise it starts now.
     */
    readonly #onPointerUp = (): void => {
        if (this.#swallow !== null && this.#swallow.until === null) {
            this.#swallow.until = performance.now() + clickAfterRelease;
        }

        this.#release();
    };

    readonly #release = (): void => {
        this.#cancelPress();
        this.#peek?.release();
    };

    readonly #onWindowBlur = (event: FocusEvent): void => {
        // An element losing focus targets that element; the window losing focus targets the window.
        if (!(event.target instanceof Node)) {
            this.#release();
        }
    };

    readonly #holdStill = (event: Event): void => {
        if (this.#peek !== null && event.cancelable) {
            event.preventDefault();
        }
    };

    readonly #onKeyDown = (event: KeyboardEvent): void => {
        if (event.key === 'Escape' && this.#peek !== null) {
            event.preventDefault();
            event.stopPropagation();
            this.#release();
        }
    };

    readonly #onDragStart = (event: DragEvent): void => {
        if (this.#peek !== null || this.#isSwallowing()) {
            // The picture is out; it must not be dragged away as well.
            event.preventDefault();
        } else {
            this.#cancelPress();
        }
    };

    /**
     * On touch screens a long press also asks for the context menu. Once a peek is out, that menu is noise.
     */
    readonly #onContextMenu = (event: Event): void => {
        if (
            this.#peek !== null ||
            this.#isSwallowing() ||
            this.#press !== null
        ) {
            event.preventDefault();
        }
    };

    readonly #onClick = (event: MouseEvent): void => {
        const swallow = this.#swallow;

        if (swallow === null) {
            return;
        }

        const isSwallowing = this.#isSwallowing();

        this.#swallow = null;

        if (
            isSwallowing &&
            event.target instanceof Node &&
            swallow.target.contains(event.target)
        ) {
            event.preventDefault();
            event.stopPropagation();
        }
    };

    readonly #cancelPress = (): void => {
        if (this.#press !== null) {
            clearTimeout(this.#press.timer);
            this.#press = null;
        }
    };

    #isSwallowing(): boolean {
        const until = this.#swallow?.until;

        return (
            until === null || (until !== undefined && performance.now() < until)
        );
    }
}
