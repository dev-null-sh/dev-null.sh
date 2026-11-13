export class PickedImageError extends Error {}

export const maxImages = 3;
// The server's File::max('10mb') allows 10,000 kilobytes.
export const maxImageBytes = 10_000 * 1024;
export const acceptedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
export const invalidImageMessage =
    'Only JPEG, PNG, WebP, HEIC, or DNG images up to 100 MB.';
const noDngPreviewMessage =
    'This DNG has no preview this browser can convert. Export it as JPEG or HEIC first.';

// Images up to this size are read, and the ones over maxImageBytes converted.
const maxReadBytes = 100 * 1024 * 1024;

// The server keeps at most 1,600 pixels per side, so a converted image loses
// nothing it would keep, and a photo that size is a fraction of maxImageBytes.
const maxConvertedDimension = 1600;

const isHeic = (file: File): boolean =>
    ['image/heic', 'image/heif'].includes(file.type) ||
    /\.hei[cf]$/i.test(file.name);

const isDng = (file: File): boolean =>
    ['image/dng', 'image/x-adobe-dng'].includes(file.type) ||
    /\.dng$/i.test(file.name);

/**
 * HEIC photos, like the ones an iPhone hands to a Mac, DNG photos, like its
 * ProRAW ones, and images over maxImageBytes are converted before they are
 * attached.
 */
export const needsConversion = (file: File): boolean =>
    isHeic(file) || isDng(file) || file.size > maxImageBytes;

/**
 * The canvas transform that draws an image stored with this TIFF orientation
 * upright, for the stored width and height. Orientations 5 to 8 swap the sides.
 */
const orientationTransforms: Partial<
    Record<
        number,
        (
            width: number,
            height: number,
        ) => [number, number, number, number, number, number]
    >
> = {
    2: (width) => [-1, 0, 0, 1, width, 0],
    3: (width, height) => [-1, 0, 0, -1, width, height],
    4: (_, height) => [1, 0, 0, -1, 0, height],
    5: () => [0, 1, 1, 0, 0, 0],
    6: (_, height) => [0, 1, -1, 0, height, 0],
    7: (width, height) => [0, -1, -1, 0, height, width],
    8: (width) => [0, -1, 1, 0, 0, width],
};

async function bytesAt(
    blob: Blob,
    offset: number,
    length: number,
): Promise<DataView> {
    const buffer = await blob.slice(offset, offset + length).arrayBuffer();

    if (buffer.byteLength < length) {
        throw new PickedImageError(noDngPreviewMessage);
    }

    return new DataView(buffer);
}

type Ifd = { tags: Map<number, number[]>; next: number };

// TIFF field types holding the numbers read here: SHORT, LONG and IFD.
const valueSizes: Partial<Record<number, number>> = { 3: 2, 4: 4, 13: 4 };

// The tags a preview is found by: its size, compression, photometric
// interpretation, strip or JPEG stream, orientation, and the SubIFDs.
const previewTags = new Set([256, 257, 259, 262, 273, 274, 279, 330, 513, 514]);

async function readIfd(
    file: Blob,
    offset: number,
    little: boolean,
): Promise<Ifd> {
    const count = (await bytesAt(file, offset, 2)).getUint16(0, little);
    const entries = await bytesAt(file, offset + 2, count * 12 + 4);
    const tags = new Map<number, number[]>();

    for (let entry = 0; entry < count; entry++) {
        const at = entry * 12;
        const tag = entries.getUint16(at, little);
        const size = valueSizes[entries.getUint16(at + 2, little)];
        const length = entries.getUint32(at + 4, little);

        if (!previewTags.has(tag) || size === undefined) continue;

        const inline = size * length <= 4;

        // Only the SubIFDs are read where their values lie outside the entry.
        // A strip table that does is several strips, and a preview is one.
        if (!inline && (tag !== 330 || length > 64)) {
            tags.set(tag, []);
            continue;
        }

        const values = inline
            ? entries
            : await bytesAt(
                  file,
                  entries.getUint32(at + 8, little),
                  size * length,
              );
        const start = inline ? at + 8 : 0;

        tags.set(
            tag,
            Array.from({ length }, (_, index) =>
                size === 2
                    ? values.getUint16(start + index * 2, little)
                    : values.getUint32(start + index * 4, little),
            ),
        );
    }

    return { tags, next: entries.getUint32(count * 12, little) };
}

/**
 * Finds the largest JPEG a DNG carries as a preview of itself, in IFD 0, its
 * SubIFDs or the IFDs chained after them, and the orientation IFD 0 gives. A
 * preview is a JPEG in RGB or YCbCr; the raw data is often lossless JPEG too,
 * but in a raw photometric interpretation, and is passed over.
 */
async function dngPreview(
    file: File,
): Promise<{ jpeg: Blob; orientation: number }> {
    const header = await bytesAt(file, 0, 8);
    const little = header.getUint16(0, false) === 0x4949;

    if (
        (!little && header.getUint16(0, false) !== 0x4d4d) ||
        header.getUint16(2, little) !== 42
    ) {
        throw new PickedImageError(noDngPreviewMessage);
    }

    const queue = [header.getUint32(4, little)];
    const seen = new Set<number>();
    let orientation = 1;
    let best: { offset: number; length: number; pixels: number } | undefined;

    while (queue.length > 0 && seen.size < 32) {
        const offset = queue.shift() ?? 0;

        if (offset === 0 || offset >= file.size || seen.has(offset)) continue;

        const first = seen.size === 0;
        seen.add(offset);

        let ifd: Ifd;

        // An IFD that runs past the end of the file is passed over, so that a
        // damaged one does not lose a preview found elsewhere.
        try {
            ifd = await readIfd(file, offset, little);
        } catch (error) {
            if (error instanceof PickedImageError) continue;
            throw error;
        }

        const { tags, next } = ifd;
        const tag = (id: number) => tags.get(id)?.[0];

        if (first) orientation = tag(274) ?? 1;
        queue.push(...(tags.get(330) ?? []), next);

        const start = tag(513) ?? tag(273);
        const length = tag(514) ?? tag(279);
        const pixels = (tag(256) ?? 0) * (tag(257) ?? 0);

        if (
            (tag(259) === 6 || tag(259) === 7) &&
            (tag(262) === 2 || tag(262) === 6) &&
            (tags.get(273)?.length ?? 1) === 1 &&
            start !== undefined &&
            length !== undefined &&
            length > 0 &&
            start + length <= file.size &&
            pixels > (best?.pixels ?? 0)
        ) {
            best = { offset: start, length, pixels };
        }
    }

    if (best === undefined) throw new PickedImageError(noDngPreviewMessage);

    return {
        jpeg: file.slice(best.offset, best.offset + best.length, 'image/jpeg'),
        orientation: orientationTransforms[orientation] ? orientation : 1,
    };
}

/**
 * The preview without its EXIF segments. An iPhone writes the orientation of
 * a ProRAW photo into the preview's EXIF as well as into IFD 0, and a browser
 * turns a JPEG by its EXIF while decoding it, so the photo would be turned
 * twice. Without the EXIF the preview decodes as it is stored, and the
 * orientation of IFD 0 turns it once.
 */
async function withoutExif(jpeg: Blob): Promise<Blob> {
    if ((await bytesAt(jpeg, 0, 2)).getUint16(0) !== 0xffd8) {
        throw new PickedImageError(noDngPreviewMessage);
    }

    const parts: Blob[] = [];
    let kept = 0;
    let at = 2;

    // The segments before the start of scan carry the metadata; the image
    // data follows it.
    while (at + 4 <= jpeg.size) {
        const segment = await bytesAt(jpeg, at, 4);
        const marker = segment.getUint16(0);

        // A marker may be preceded by fill bytes of 0xFF.
        if (marker === 0xffff) {
            at += 1;
            continue;
        }

        if (marker === 0xffda || marker >> 8 !== 0xff) break;

        const end = at + 2 + segment.getUint16(2);
        const exif =
            marker === 0xffe1 &&
            end - at >= 10 &&
            (await jpeg.slice(at + 4, at + 10).text()) === 'Exif\0\0';

        if (exif) {
            parts.push(jpeg.slice(kept, at));
            kept = end;
        }

        at = end;
    }

    parts.push(jpeg.slice(kept));

    return new Blob(parts, { type: 'image/jpeg' });
}

/**
 * Decodes the image, and for a DNG its preview. A HEIC photo the browser
 * can't decode, as Safari can, is decoded with libheif, which is only loaded
 * then.
 */
async function decode(
    file: File,
): Promise<{ bitmap: ImageBitmap; orientation: number }> {
    if (isDng(file)) {
        const { jpeg, orientation } = await dngPreview(file);
        // A preview the browser can't decode is as good as none.
        const bitmap = await createImageBitmap(await withoutExif(jpeg)).catch(
            () => {
                throw new PickedImageError(noDngPreviewMessage);
            },
        );

        return { bitmap, orientation };
    }

    const bitmap = await createImageBitmap(file).catch(
        async (error: unknown) => {
            if (!isHeic(file)) throw error;

            const { heicTo } = await import('heic-to');

            return heicTo({ blob: file, type: 'bitmap' });
        },
    );

    return { bitmap, orientation: 1 };
}

/**
 * Re-encodes the image as WebP at the size the server keeps, upright. A
 * browser that can't encode WebP gives PNG, which the server accepts too.
 */
async function convert(file: File): Promise<File> {
    const { bitmap, orientation } = await decode(file);
    const scale = Math.min(
        1,
        maxConvertedDimension / Math.max(bitmap.width, bitmap.height),
    );
    const width = Math.round(bitmap.width * scale);
    const height = Math.round(bitmap.height * scale);
    const turned = orientation >= 5;
    const canvas = new OffscreenCanvas(
        turned ? height : width,
        turned ? width : height,
    );
    const context = canvas.getContext('2d');
    const transform = orientationTransforms[orientation];

    if (transform !== undefined)
        context?.transform(...transform(width, height));
    context?.drawImage(bitmap, 0, 0, width, height);
    bitmap.close();

    const blob = await canvas.convertToBlob({
        type: 'image/webp',
        quality: 0.9,
    });
    const extension = blob.type.replace('image/', '');
    const name = `${file.name.replace(/\.[^.]*$/, '')}.${extension}`;

    return new File([blob], name, { type: blob.type });
}

/**
 * Copy provider-backed files before reading metadata or creating preview URLs.
 * Android photo providers can report changing timestamps, causing Chromium to
 * reject later reads with ERR_UPLOAD_FILE_CHANGED (crbug.com/40123366).
 */
async function readPickedImage(file: File): Promise<File> {
    if (
        !acceptedImageTypes.includes(file.type) &&
        !isHeic(file) &&
        !isDng(file)
    ) {
        throw new PickedImageError(invalidImageMessage);
    }

    // Bound the copy without inspecting the provider's size/lastModified.
    const reader = file.stream().getReader();
    const chunks: Uint8Array<ArrayBuffer>[] = [];
    let bytes = 0;

    try {
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            bytes += value.byteLength;
            if (bytes > maxReadBytes) {
                throw new PickedImageError(invalidImageMessage);
            }
            chunks.push(new Uint8Array(value));
        }
    } finally {
        await reader.cancel().catch(() => {});
        reader.releaseLock();
    }

    if (bytes === 0) {
        throw new PickedImageError(
            'This image is empty. Please select another image.',
        );
    }

    const copy = new File(chunks, file.name, { type: file.type });

    return needsConversion(copy) ? convert(copy) : copy;
}

export async function readPickedImages(
    files: File[],
    capacity = maxImages,
): Promise<{ images: File[]; errors: unknown[] }> {
    const images: File[] = [];
    const errors: unknown[] = [];

    for (const file of files) {
        if (images.length >= capacity) {
            errors.push(
                new PickedImageError(`Up to ${maxImages} images per commit.`),
            );
            break;
        }

        try {
            images.push(await readPickedImage(file));
        } catch (error) {
            errors.push(error);
        }
    }

    return { images, errors };
}
