function luminance(r: number, g: number, b: number): number {
    const [lr, lg, lb] = [r, g, b].map((channel) => {
        const c = channel / 255;
        return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * lr + 0.7152 * lg + 0.0722 * lb;
}

export type Visibility = 'easy' | 'partly' | 'wcag' | 'hard';

// What a pixel is to visibility(); 0 is one too transparent to matter (under half opaque).
const shows = 1;
const vanishes = 2;
const seen = 3;

// How a square image's RGBA pixels show over a #rrggbb background, each laid over it. A pixel
// shows at 3:1, WCAG's minimum for graphics, and is faint at 1.5:1: there, but short of it. The
// image is hard to see when fewer than 3% of its pixels show, so a thin logo still counts, and
// 'wcag' when 3% are at least faint. An image at least 3/4 opaque, such as a photo or a logo on
// its own square, rounded or round (a circle fills 79%), brings its own background: it is only hard
// to see when it nearly vanishes, fewer than 3% of it faint. Otherwise the image is partly hard to
// see when a connected part of it, 3% of it or more and at least half opaque, vanishes into the
// background. These parts don't count: one closed in by pixels that show, such as the hole in a
// letter, and ones that touch what shows and together surround all of it, such as a white square
// behind a dark logo (even one the logo cuts in two), its backing.
export function visibility(
    pixels: Uint8ClampedArray,
    background: string,
): Visibility {
    const [br, bg, bb] = [1, 3, 5].map((i) =>
        Number.parseInt(background.slice(i, i + 2), 16),
    );
    const backgroundLuminance = luminance(br, bg, bb);
    const count = pixels.length / 4;
    const side = Math.sqrt(count);
    const minimum = count * 0.03;
    const kinds = new Uint8Array(count);
    // The box around the pixels that show: [left, top, right, bottom].
    const box = [side, side, -1, -1];
    let shown = 0;
    // Pixels that are at least faint, shown ones included.
    let faint = 0;
    let solid = 0;
    for (let p = 0; p < count; p++) {
        const i = p * 4;
        const alpha = pixels[i + 3] / 255;
        const pixelLuminance = luminance(
            pixels[i] * alpha + br * (1 - alpha),
            pixels[i + 1] * alpha + bg * (1 - alpha),
            pixels[i + 2] * alpha + bb * (1 - alpha),
        );
        const contrast =
            (Math.max(pixelLuminance, backgroundLuminance) + 0.05) /
            (Math.min(pixelLuminance, backgroundLuminance) + 0.05);
        if (contrast >= 3) {
            kinds[p] = shows;
            shown++;
            grow(box, p % side, Math.floor(p / side));
        } else if (alpha >= 0.5) {
            kinds[p] = vanishes;
        }
        if (contrast >= 1.5) {
            faint++;
        }
        if (alpha >= 0.5) {
            solid++;
        }
    }
    if (solid >= count * 0.75) {
        return faint >= minimum ? 'easy' : 'hard';
    }
    if (shown < minimum) {
        return faint >= minimum ? 'wcag' : 'hard';
    }
    // The box around the vanishing parts that may be a backing, all together.
    const parts = [side, side, -1, -1];
    for (let start = 0; start < count; start++) {
        if (kinds[start] !== vanishes) {
            continue;
        }
        // Gather this vanishing part, the box around it, whether it reaches the background, and
        // whether it touches a pixel that shows.
        const part = [start];
        const around = [side, side, -1, -1];
        kinds[start] = seen;
        let size = 0;
        let open = false;
        let touches = false;
        while (part.length > 0) {
            const p = part.pop()!;
            const x = p % side;
            const y = (p - x) / side;
            size++;
            grow(around, x, y);
            if (x === 0 || y === 0 || x === side - 1 || y === side - 1) {
                open = true;
            }
            for (
                let ny = Math.max(y - 1, 0);
                ny <= Math.min(y + 1, side - 1);
                ny++
            ) {
                for (
                    let nx = Math.max(x - 1, 0);
                    nx <= Math.min(x + 1, side - 1);
                    nx++
                ) {
                    const q = ny * side + nx;
                    if (kinds[q] === 0) {
                        open = true;
                    } else if (kinds[q] === shows) {
                        touches = true;
                    } else if (kinds[q] === vanishes) {
                        kinds[q] = seen;
                        part.push(q);
                    }
                }
            }
        }
        if (!open || size < minimum) {
            continue;
        }
        // A part apart from everything that shows can't be its backing.
        if (!touches) {
            return 'partly';
        }
        grow(parts, around[0], around[1]);
        grow(parts, around[2], around[3]);
    }
    if (parts[2] < 0) {
        return 'easy';
    }
    const backs =
        parts[0] <= box[0] &&
        parts[1] <= box[1] &&
        parts[2] >= box[2] &&
        parts[3] >= box[3];
    return backs ? 'easy' : 'partly';
}

function grow(box: number[], x: number, y: number): void {
    box[0] = Math.min(box[0], x);
    box[1] = Math.min(box[1], y);
    box[2] = Math.max(box[2], x);
    box[3] = Math.max(box[3], y);
}

// How many times finer than shown readPixels() samples an image, so a stroke thinner than a pixel
// keeps its color instead of being smoothed into the background.
const fineness = 4;

// A loaded image's pixels as a centered square of `size` pixels, the size it will be shown at.
export function readPixels(
    image: HTMLImageElement,
    size: number,
): Uint8ClampedArray {
    const fine = size * fineness;
    const side = Math.min(image.naturalWidth, image.naturalHeight);
    const canvas = document.createElement('canvas');
    canvas.width = fine;
    canvas.height = fine;
    const context = canvas.getContext('2d', { willReadFrequently: true })!;
    context.drawImage(
        image,
        (image.naturalWidth - side) / 2,
        (image.naturalHeight - side) / 2,
        side,
        side,
        0,
        0,
        fine,
        fine,
    );
    return mostOpaque(context.getImageData(0, 0, fine, fine).data, fineness);
}

// Square RGBA pixels shrunk by `scale`, which must divide their side, each block of them standing
// for itself by its most opaque pixel (the first, in a tie): a thin line keeps its color, and a
// translucent area its translucency.
export function mostOpaque(
    pixels: Uint8ClampedArray,
    scale: number,
): Uint8ClampedArray {
    const fine = Math.sqrt(pixels.length / 4);
    const size = fine / scale;
    const shrunk = new Uint8ClampedArray(size * size * 4);
    for (let y = 0; y < fine; y++) {
        for (let x = 0; x < fine; x++) {
            const from = (y * fine + x) * 4;
            const to =
                (Math.floor(y / scale) * size + Math.floor(x / scale)) * 4;
            if (pixels[from + 3] > shrunk[to + 3]) {
                shrunk.set(pixels.subarray(from, from + 4), to);
            }
        }
    }
    return shrunk;
}
